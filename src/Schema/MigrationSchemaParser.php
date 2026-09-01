<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use CalebDW\PhpstanLaravel\Support\ModelHelper;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;

use function array_key_exists;
use function array_merge;
use function array_pop;
use function class_basename;
use function count;
use function end;
use function in_array;
use function is_string;
use function property_exists;
use function strtolower;

/** @see https://github.com/psalm/laravel-psalm-plugin/blob/master/src/SchemaAggregator.php */
final class MigrationSchemaParser
{
    /** @var list<Connection> */
    private array $connectionStack = [];

    private NodeFinder $nodeFinder;

    private ObjectType $schemaFacadeType;

    private ObjectType $blueprintType;

    /** @var array<string, bool> */
    private array $schemaFacades = [];

    public function __construct(
        private ModelSchema $modelSchema,
        private ModelHelper $modelHelper,
        private ReflectionProvider $reflectionProvider,
    ) {
        $this->nodeFinder       = new NodeFinder();
        $this->schemaFacadeType = new ObjectType(Schema::class);
        $this->blueprintType    = new ObjectType(Blueprint::class);
    }

    /**
     * Is the given class name the Schema facade?
     *
     * Migrations reference the facade on nearly every statement, so the
     * subtype check is remembered per class name rather than repeated.
     */
    private function isSchemaFacade(Name $class): bool
    {
        $className = $class->toCodeString();

        if ($className === Schema::class || $className === '\Schema') {
            return true;
        }

        return $this->schemaFacades[$className] ??= $this->schemaFacadeType
            ->isSuperTypeOf(new ObjectType($className))
            ->yes();
    }

    /** @param  array<int, Stmt> $stmts */
    public function addStatements(array $stmts): void
    {
        foreach ($this->nodeFinder->findInstanceOf($stmts, Class_::class) as $stmt) {
            $this->addClassStatements($stmt->stmts);
        }
    }

    /** @param  array<int, Stmt> $stmts */
    private function addClassStatements(array $stmts): void
    {
        $connectionName = null;

        foreach ($this->nodeFinder->findInstanceOf($stmts, Property::class) as $method) {
            if ($method->props[0]->name->name !== 'connection') {
                continue;
            }

            if ($method->props[0]->default instanceof String_) {
                $connectionName = $method->props[0]->default->value;

                break;
            }
        }

        $this->connectionStack[] = $this->modelSchema->getOrCreateConnection($connectionName);

        foreach ($stmts as $stmt) {
            if (
                ! ($stmt instanceof ClassMethod)
                || $stmt->name->name === 'down'
                || ! $stmt->stmts
            ) {
                continue;
            }

            $this->addUpMethodStatements($stmt->stmts);
        }

        array_pop($this->connectionStack);
    }

    /** @param  Stmt[] $stmts */
    private function addUpMethodStatements(array $stmts): void
    {
        $schemaVariables = [];

        foreach ($this->nodeFinder->findInstanceOf($stmts, Expression::class) as $stmt) {
            $connection = null;

            if (
                $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof Variable
                && is_string($stmt->expr->var->name)
            ) {
                $variable = $stmt->expr->var->name;
                unset($schemaVariables[$variable]);

                if (
                    $stmt->expr->expr instanceof StaticCall
                    && $this->isSchemaConnectionCall($stmt->expr->expr)
                ) {
                    $schemaVariables[$variable] = $this->getConnectionName($stmt->expr->expr);
                }

                continue;
            }

            if (
                $stmt->expr instanceof MethodCall
                && $stmt->expr->var instanceof StaticCall
                && $stmt->expr->var->class instanceof Name
                && $stmt->expr->var->name instanceof Identifier
                && in_array($stmt->expr->var->name->name, ['connection', 'setConnection'], strict: true)
                && $this->isSchemaFacade($stmt->expr->var->class)
            ) {
                $statement = $stmt->expr;
                $args      = $stmt->expr->var->getArgs();
                if (count($args) > 0) {
                    $connectionArg = $args[0]->value;
                    if ($connectionArg instanceof String_) {
                        $connection = $connectionArg->value;
                    }
                }
            } elseif (
                $stmt->expr instanceof MethodCall
                && $stmt->expr->var instanceof Variable
                && is_string($stmt->expr->var->name)
                && array_key_exists($stmt->expr->var->name, $schemaVariables)
            ) {
                $statement  = $stmt->expr;
                $connection = $schemaVariables[$stmt->expr->var->name];
            } elseif (
                $stmt->expr instanceof StaticCall
                && $stmt->expr->class instanceof Name
                && $stmt->expr->name instanceof Identifier
                && $this->isSchemaFacade($stmt->expr->class)
            ) {
                $statement = $stmt->expr;
            } else {
                continue;
            }

            if (! $statement->name instanceof Identifier) {
                continue;
            }

            if ($connection !== null) {
                $this->connectionStack[] = $this->modelSchema->getOrCreateConnection($connection);
            }

            match ($statement->name->name) {
                'create'               => $this->alterTable($statement, creating: true),
                'table'                => $this->alterTable($statement, creating: false),
                'drop', 'dropIfExists' => $this->dropTable($statement),
                'rename'               => $this->renameTableThroughStaticCall($statement),
                default                => null,
            };

            if ($connection === null) {
                continue;
            }

            array_pop($this->connectionStack);
        }
    }

    private function isSchemaConnectionCall(Expr $expr): bool
    {
        return $expr instanceof StaticCall
            && $expr->class instanceof Name
            && $expr->name instanceof Identifier
            && in_array($expr->name->name, ['connection', 'setConnection'], strict: true)
            && $this->isSchemaFacade($expr->class);
    }

    private function getConnectionName(StaticCall $call): string|null
    {
        $connection = $call->getArgs()[0]->value ?? null;

        return $connection instanceof String_ ? $connection->value : null;
    }

    private function alterTable(StaticCall|MethodCall $call, bool $creating): void
    {
        if (! isset($call->args[0])) {
            return;
        }

        $value = $call->getArgs()[0]->value;

        $tableName = null;

        if ($value instanceof String_) {
            $tableName = $value->value;
        }

        if ($value instanceof ClassConstFetch) {
            if (
                ! $value->class instanceof Name
                || ! $value->name instanceof Identifier
            ) {
                return;
            }

            if ($value->class instanceof FullyQualified) {
                $className = $value->class->name;
            } else {
                $resolvedName = $value->class->getAttribute('resolvedName');

                if (! $resolvedName instanceof FullyQualified) {
                    return;
                }

                $className = $resolvedName->name;
            }

            if (! $this->reflectionProvider->hasClass($className)) {
                return;
            }

            $class = $this->reflectionProvider->getClass($className);

            $constantValueType = $class->getConstant($value->name->toString())->getValueType();

            if ($constantValueType->getConstantStrings() !== []) {
                $tableName = $constantValueType->getConstantStrings()[0]->getValue();
            }
        }

        if ($tableName === null) {
            return;
        }

        if ($creating) {
            $this->getCurrentConnection()->setTable(new Table($tableName));
        }

        if (
            ! isset($call->args[1])
            || ! $call->getArgs()[1]->value instanceof Closure
            || count($call->getArgs()[1]->value->params) < 1
            || (
                $call->getArgs()[1]->value->params[0]->type instanceof Name
                && ! $this->blueprintType->isSuperTypeOf(new ObjectType($call->getArgs()[1]->value->params[0]->type->toCodeString()))->yes()
            )
        ) {
            return;
        }

        $updateClosure = $call->getArgs()[1]->value;

        if (
            ! ($call->getArgs()[1]->value->params[0]->var instanceof Variable)
            || ! is_string($call->getArgs()[1]->value->params[0]->var->name)
        ) {
            return;
        }

        $argName = $call->getArgs()[1]->value->params[0]->var->name;

        $this->processColumnUpdates($tableName, $argName, $this->getUpdateStatements($updateClosure));
    }

    /**
     * @param  Stmt[] $stmts
     *
     * @throws Exception
     */
    private function processColumnUpdates(string $tableName, string $argName, array $stmts): void
    {
        if (! isset($this->getCurrentConnection()->tables[$tableName])) {
            return;
        }

        $table = $this->getCurrentConnection()->tables[$tableName];

        foreach ($stmts as $stmt) {
            if (
                ! ($stmt instanceof Expression)
                || ! ($stmt->expr instanceof MethodCall)
                || ! ($stmt->expr->name instanceof Identifier)
            ) {
                continue;
            }

            $rootVar = $stmt->expr;

            $firstMethodCall = $rootVar;

            $nullable = false;

            while ($rootVar instanceof MethodCall) {
                if (
                    $rootVar->name instanceof Identifier
                    && $rootVar->name->name === 'nullable'
                    && $this->getNullableArgumentValue($rootVar) === true
                ) {
                    $nullable = true;
                }

                $firstMethodCall = $rootVar;
                $rootVar         = $rootVar->var;
            }

            if (
                ! ($rootVar instanceof Variable)
                || $rootVar->name !== $argName
                || ! ($firstMethodCall->name instanceof Identifier)
            ) {
                continue;
            }

            $firstArg  = $firstMethodCall->getArgs()[0]->value ?? null;
            $secondArg = $firstMethodCall->getArgs()[1]->value ?? null;

            if ($firstMethodCall->name->name === 'foreignIdFor') {
                if (
                    $firstArg instanceof ClassConstFetch
                    && $firstArg->class instanceof Name
                ) {
                    $modelClass = $firstArg->class->toCodeString();
                } elseif ($firstArg instanceof String_) {
                    $modelClass = $firstArg->value;
                } else {
                    continue;
                }

                $columnName = Str::snake(class_basename($modelClass)) . '_id';
                if ($secondArg instanceof String_) {
                    $columnName = $secondArg->value;
                }

                /** @phpstan-ignore argument.type (not a class string) */
                $model = $this->modelHelper->getModelInstance($modelClass);

                if ($model === null) {
                    continue;
                }

                $type = $this->modelSchema->hasModelColumn($model, $model->getKeyName())
                    ? $this->modelSchema->getModelColumn($model, $model->getKeyName())->readableType
                    : 'int';
                $table->setColumn(new Column($columnName, $type, $nullable));

                continue;
            }

            if (! $firstArg instanceof String_) {
                if ($firstArg instanceof Array_ && $firstMethodCall->name->name === 'dropColumn') {
                    foreach ($firstArg->items as $arrayItem) {
                        if (! $arrayItem->value instanceof String_) {
                            continue;
                        }

                        $table->dropColumn($arrayItem->value->value);
                    }
                }

                switch (strtolower($firstMethodCall->name->name)) {
                    case 'droptimestamps':
                    case 'droptimestampstz':
                        $table->dropColumn('created_at');
                        $table->dropColumn('updated_at');
                        continue 2;

                    case 'remembertoken':
                        $table->setColumn(new Column('remember_token', 'string', true));
                        continue 2;

                    case 'dropremembertoken':
                        $table->dropColumn('remember_token');
                        continue 2;

                    case 'timestamps':
                    case 'timestampstz':
                    case 'nullabletimestamps':
                    case 'nullabletimestampstz':
                        $table->setColumn(new Column('created_at', 'string', true));
                        $table->setColumn(new Column('updated_at', 'string', true));
                        continue 2;
                }

                $defaultsMap = [
                    'softDeletes' => 'deleted_at',
                    'softDeletesTz' => 'deleted_at',
                    'softDeletesDatetime' => 'deleted_at',
                    'dropSoftDeletes' => 'deleted_at',
                    'dropSoftDeletesTz' => 'deleted_at',
                    'uuid' => 'uuid',
                    'id' => 'id',
                    'ulid' => 'ulid',
                    'ipAddress' => 'ip_address',
                    'macAddress' => 'mac_address',
                ];
                if (! array_key_exists($firstMethodCall->name->name, $defaultsMap)) {
                    continue;
                }

                $columnName = $defaultsMap[$firstMethodCall->name->name];
            } else {
                $columnName = $firstArg->value;
            }

            $secondArgArray = null;

            if ($secondArg instanceof Array_) {
                $secondArgArray = [];

                foreach ($secondArg->items as $arrayItem) {
                    if (! $arrayItem->value instanceof String_) {
                        continue;
                    }

                    $secondArgArray[] = $arrayItem->value->value;
                }
            }

            $this->processStatementAlterMethod(
                strtolower($firstMethodCall->name->name),
                $firstMethodCall,
                $table,
                $columnName,
                $nullable,
                $secondArg,
                $argName,
                $tableName,
                $secondArgArray,
                $stmt,
            );
        }
    }

    private function dropTable(StaticCall|MethodCall $call): void
    {
        if (
            ! isset($call->args[0])
            || ! $call->getArgs()[0]->value instanceof String_
        ) {
            return;
        }

        $tableName = $call->getArgs()[0]->value->value;

        $this->getCurrentConnection()->dropTable($tableName);
    }

    private function renameTableThroughStaticCall(StaticCall|MethodCall $call): void
    {
        if (
            ! isset($call->args[0], $call->args[1])
            || ! $call->getArgs()[0]->value instanceof String_
            || ! $call->getArgs()[1]->value instanceof String_
        ) {
            return;
        }

        $oldTableName = $call->getArgs()[0]->value->value;
        $newTableName = $call->getArgs()[1]->value->value;

        $this->getCurrentConnection()->renameTable($oldTableName, $newTableName);
    }

    private function renameTableThroughMethodCall(Table $oldTable, MethodCall $call): void
    {
        if (
            ! isset($call->args[0])
            || ! $call->getArgs()[0]->value instanceof String_
        ) {
            return;
        }

        /** @var String_ $methodCallArgument */
        $methodCallArgument = $call->getArgs()[0]->value;

        $oldTableName = $oldTable->name;
        $newTableName = $methodCallArgument->value;

        $this->getCurrentConnection()->renameTable($oldTableName, $newTableName);
    }

    private function getNullableArgumentValue(MethodCall $rootVar): bool
    {
        if (! array_key_exists(0, $rootVar->args)) {
            return true;
        }

        $arg = $rootVar->args[0];

        if (! ($arg instanceof Arg)) {
            return true;
        }

        $argExpression = $arg->value;

        if (! ($argExpression instanceof ConstFetch)) {
            return true;
        }

        return $argExpression->name->getFirst() === 'true';
    }

    /** @return Expression[] */
    private function getUpdateStatements(Expr $updateClosure): array
    {
        if (! property_exists($updateClosure, 'stmts')) {
            return [];
        }

        $statements = [];

        foreach ($updateClosure->stmts as $updateStatement) {
            if ($updateStatement instanceof If_) {
                $statements = array_merge(
                    $statements,
                    $this->nodeFinder->findInstanceOf($updateStatement, Expression::class),
                );

                continue;
            }

            $statements[] = $updateStatement;
        }

        return $statements;
    }

    /**
     * @param array<int, mixed> $secondArgArray
     *
     * @throws Exception
     */
    private function processStatementAlterMethod(
        string $method,
        MethodCall|null $firstMethodCall,
        Table $table,
        string $columnName,
        bool $nullable,
        mixed $secondArg,
        Expr|string $argName,
        string $tableName,
        array|null $secondArgArray,
        Expression $stmt,
    ): void {
        switch ($method) {
            case 'addcolumn':
                $this->processStatementAlterMethod(
                    strtolower($firstMethodCall->args[0]->value->value ?? ''),
                    null,
                    $table,
                    $firstMethodCall->args[1]->value->value ?? '',
                    $nullable,
                    $secondArg,
                    $argName,
                    $tableName,
                    $secondArgArray,
                    $stmt,
                );

                return;

            case 'biginteger':
            case 'increments':
            case 'id':
            case 'integer':
            case 'integerincrements':
            case 'mediumincrements':
            case 'mediuminteger':
            case 'smallincrements':
            case 'smallinteger':
            case 'tinyincrements':
            case 'tinyinteger':
            case 'unsignedbiginteger':
            case 'unsignedinteger':
            case 'unsignedmediuminteger':
            case 'unsignedsmallinteger':
            case 'unsignedtinyinteger':
            case 'bigincrements':
            case 'foreignid':
            case 'year':
                $table->setColumn(new Column($columnName, 'int', $nullable));

                return;

            case 'char':
            case 'datetimetz':
            case 'date':
            case 'datetime':
            case 'ipaddress':
            case 'json':
            case 'jsonb':
            case 'linestring':
            case 'longtext':
            case 'macaddress':
            case 'mediumtext':
            case 'multilinestring':
            case 'string':
            case 'text':
            case 'time':
            case 'timestamp':
            case 'timestamptz':
            case 'timetz':
            case 'ulid':
            case 'uuid':
            case 'binary':
                $table->setColumn(new Column($columnName, 'string', $nullable));

                return;

            case 'boolean':
                $table->setColumn(new Column($columnName, 'bool', $nullable));

                return;

            case 'geometry':
            case 'geometrycollection':
            case 'multipoint':
            case 'multipolygon':
            case 'multipolygonz':
            case 'point':
            case 'polygon':
            case 'computed':
                $table->setColumn(new Column($columnName, 'mixed', $nullable));

                return;

            case 'double':
            case 'float':
            case 'unsigneddecimal':
            case 'decimal':
                $table->setColumn(new Column($columnName, 'float', $nullable));

                return;

            case 'after':
                if (
                    $secondArg instanceof Closure
                    && $secondArg->params[0]->var instanceof Variable
                    && ! ($secondArg->params[0]->var->name instanceof Expr)
                ) {
                    $argName = $secondArg->params[0]->var->name;
                    $this->processColumnUpdates($tableName, $argName, $secondArg->stmts);
                }

                return;

            case 'dropcolumn':
            case 'dropifexists':
            case 'dropsoftdeletes':
            case 'dropsoftdeletestz':
            case 'removecolumn':
            case 'drop':
                $table->dropColumn($columnName);

                return;

            case 'dropforeign':
            case 'dropindex':
            case 'dropprimary':
            case 'dropunique':
            case 'foreign':
            case 'index':
            case 'primary':
            case 'renameindex':
            case 'spatialindex':
            case 'unique':
            case 'dropspatialindex':
                return;

            case 'dropmorphs':
                $table->dropColumn($columnName . '_type');
                $table->dropColumn($columnName . '_id');

                return;

            case 'enum':
                $table->setColumn(new Column($columnName, 'enum', $nullable, $secondArgArray));

                return;

            case 'morphs':
                $table->setColumn(new Column($columnName . '_type', 'string', $nullable));
                $table->setColumn(new Column($columnName . '_id', 'int', $nullable));

                return;

            case 'nullablemorphs':
                $table->setColumn(new Column($columnName . '_type', 'string', true));
                $table->setColumn(new Column($columnName . '_id', 'int', true));

                return;

            case 'nullableuuidmorphs':
                $table->setColumn(new Column($columnName . '_type', 'string', true));
                $table->setColumn(new Column($columnName . '_id', 'string', true));

                return;

            case 'rename':
                /** @var MethodCall $methodCall */
                $methodCall = $stmt->expr;
                $this->renameTableThroughMethodCall($table, $methodCall);

                return;

            case 'renamecolumn':
                if ($secondArg instanceof String_) {
                    $table->renameColumn($columnName, $secondArg->value);
                }

                return;

            case 'set':
                $table->setColumn(new Column($columnName, 'set', $nullable, $secondArgArray));

                return;

            case 'softdeletes':
            case 'softdeletesdatetime':
            case 'softdeletestz':
                $table->setColumn(new Column($columnName, 'string', true));

                return;

            case 'uuidmorphs':
                $table->setColumn(new Column($columnName . '_type', 'string', $nullable));
                $table->setColumn(new Column($columnName . '_id', 'string', $nullable));

                return;

            default:
                // We know a property exists with a name, we just don't know its type.
                $table->setColumn(new Column($columnName, 'mixed', $nullable));
        }
    }

    private function getCurrentConnection(): Connection
    {
        $connection = end($this->connectionStack);

        if ($connection === false) {
            throw new Exception('Connection not found');
        }

        return $connection;
    }
}
