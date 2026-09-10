<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectShapeType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;

use function array_key_exists;
use function array_pad;
use function array_shift;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_int;
use function is_numeric;
use function is_string;
use function str_contains;
use function strtolower;

final class ValidationHelper
{
    /** @var array<string, Type|null> */
    private array $shapes = [];

    /** @var array<string, array<string, Type>> */
    private array $properties = [];

    public function __construct(
        private Parser $parser,
        private ReflectionProvider $reflectionProvider,
        private TypeHelper $typeHelper,
        private CallHelper $callHelper,
    ) {
    }

    public function validatedShape(ClassReflection $class): Type|null
    {
        if (! $class->is(FormRequest::class)) {
            return null;
        }

        $key = $class->getCacheKey();

        if (array_key_exists($key, $this->shapes)) {
            return $this->shapes[$key];
        }

        $fields = $this->fields($class);

        if ($fields === []) {
            return $this->shapes[$key] = null;
        }

        $shape                  = $this->shape($fields);
        $this->properties[$key] = $this->topLevel($shape);

        return $this->shapes[$key] = $shape;
    }

    public function propertyType(ClassReflection $class, string $name): Type|null
    {
        $this->validatedShape($class);

        return $this->properties[$class->getCacheKey()][$name] ?? null;
    }

    public function shapeFromRulesExpr(Expr $expr, Scope $scope): Type|null
    {
        if (! $expr instanceof Array_) {
            return null;
        }

        $fields = $this->fieldsFromArray($expr, $scope->getClassReflection(), $scope);

        return $fields === [] ? null : $this->shape($fields);
    }

    public function objectShape(Type $shape): ObjectShapeType|null
    {
        $properties = $this->topLevel($shape);

        return $properties === [] ? null : new ObjectShapeType($properties, []);
    }

    /** @param list<string> $keys */
    public function pick(Type $shape, array $keys): Type
    {
        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($keys as $key) {
            $offset = new ConstantStringType($key);
            $builder->setOffsetValueType(
                $offset,
                $shape->getOffsetValueType($offset),
                ! $shape->hasOffsetValueType($offset)->yes(),
            );
        }

        return $builder->getArray();
    }

    public function shapeFromRulesArg(CallLike $call, Scope $scope, string $name = 'rules', int $position = 1): Type|null
    {
        $rules = $call->getArg($name, $position);

        return $rules === null ? null : $this->shapeFromRulesExpr($rules->value, $scope);
    }

    public function validator(Type $shape, bool $concrete = true): Type
    {
        return new GenericObjectType($concrete ? Validator::class : ValidatorContract::class, [$shape]);
    }

    public function validatedShapeFromType(Type $type): Type|null
    {
        $shapes = [];

        foreach (TypeUtils::flattenTypes($type) as $member) {
            $shape = $member->getTemplateType(Validator::class, 'TValidated');

            if ($shape instanceof ErrorType) {
                $shape = $member->getTemplateType(ValidatorContract::class, 'TValidated');
            }

            if ($shape instanceof ErrorType) {
                continue;
            }

            $shapes[] = $shape;
        }

        return $shapes === [] ? null : TypeCombinator::union(...$shapes);
    }

    /** @return array<string, array{required: bool, nullable: bool, type: Type}> */
    private function fields(ClassReflection $class): array
    {
        if (! $class->hasNativeMethod('rules')) {
            return [];
        }

        $file = $class->getNativeMethod('rules')->getDeclaringClass()->getFileName();

        if ($file === null) {
            return [];
        }

        try {
            $stmts = $this->parser->parseFile($file);
        } catch (ParserErrorsException) {
            return [];
        }

        $method = $this->rulesMethod($stmts, $class->getNativeMethod('rules')->getDeclaringClass()->getName());

        if ($method?->stmts === null) {
            return [];
        }

        $fields = [];

        foreach ((new NodeFinder())->findInstanceOf($method->stmts, Return_::class) as $return) {
            if (! $return->expr instanceof Array_) {
                continue;
            }

            foreach ($this->fieldsFromArray($return->expr, $class) as $path => $field) {
                $fields[$path] = $field;
            }
        }

        return $fields;
    }

    /** @param  array<int, Node> $stmts */
    private function rulesMethod(array $stmts, string $className): ClassMethod|null
    {
        foreach ((new NodeFinder())->findInstanceOf($stmts, Class_::class) as $node) {
            $name = $node->namespacedName?->toString() ?? $node->name?->toString();

            if ($name !== $className) {
                continue;
            }

            foreach ($node->getMethods() as $method) {
                if ($method->name->toString() === 'rules') {
                    return $method;
                }
            }
        }

        return null;
    }

    /** @return array<string, array{required: bool, nullable: bool, type: Type}> */
    private function fieldsFromArray(Array_ $array, ClassReflection|null $class, Scope|null $scope = null): array
    {
        $fields = [];

        foreach ($array->items as $item) {
            if ($item->key === null) {
                continue;
            }

            $path = $this->stringExpr($item->key);

            if ($path === null) {
                continue;
            }

            $fields[$path] = $this->fieldFromRules($item->value, $class, $scope);
        }

        return $fields;
    }

    /** @return array{required: bool, nullable: bool, type: Type} */
    private function fieldFromRules(Expr $expr, ClassReflection|null $class, Scope|null $scope = null): array
    {
        $tokens   = $this->ruleTokens($expr, $class, $scope);
        $required = in_array('required', $tokens['names'], true);
        $nullable = in_array('nullable', $tokens['names'], true);

        return [
            'required' => $required && ! in_array('sometimes', $tokens['names'], true),
            'nullable' => $nullable,
            'type' => $this->valueType($tokens),
        ];
    }

    /** @return array{names: list<string>, enum: string|null, min: int|null, max: int|null, in: list<Type>} */
    private function ruleTokens(Expr $expr, ClassReflection|null $class, Scope|null $scope = null): array
    {
        $names = [];
        $enum  = null;
        $min   = null;
        $max   = null;
        $in    = [];

        foreach ($this->ruleExprs($expr) as $rule) {
            if ($rule instanceof String_) {
                foreach (explode('|', $rule->value) as $token) {
                    [$name, $arg] = array_pad(explode(':', $token, 2), 2, null);
                    $name         = strtolower((string) $name);
                    $names[]      = $name;

                    if ($name === 'enum' && is_string($arg) && $arg !== '' && $this->reflectionProvider->hasClass($arg)) {
                        $enum = $this->reflectionProvider->getClass($arg)->getName();
                    }

                    if ($name === 'min') {
                        $min = $this->intParam($arg);
                    }

                    if ($name === 'max') {
                        $max = $this->intParam($arg);
                    }

                    if ($name === 'between' && is_string($arg)) {
                        [$low, $high] = array_pad(explode(',', $arg, 2), 2, null);
                        $min          = $this->intParam($low);
                        $max          = $this->intParam($high);
                    }

                    if ($name !== 'in' || ! is_string($arg) || $arg === '') {
                        continue;
                    }

                    foreach (explode(',', $arg) as $value) {
                        $in[] = new ConstantStringType($value);
                    }
                }

                continue;
            }

            $enumClass = $this->enumClass($rule, $class, $scope);

            if ($enumClass !== null) {
                $names[] = 'enum';
                $enum    = $enumClass;
            }

            foreach ($this->inValues($rule, $class, $scope) as $value) {
                $names[] = 'in';
                $in[]    = $value;
            }
        }

        return ['names' => $names, 'enum' => $enum, 'min' => $min, 'max' => $max, 'in' => $in];
    }

    /** @return list<Expr> */
    private function ruleExprs(Expr $expr): array
    {
        if (! $expr instanceof Array_) {
            return [$expr];
        }

        $rules = [];

        foreach ($expr->items as $item) {
            $rules[] = $item->value;
        }

        return $rules;
    }

    private function enumClass(Expr $expr, ClassReflection|null $inClass, Scope|null $scope = null): string|null
    {
        $args = $this->ruleCallArgs($expr, $inClass, Enum::class, 'enum', $scope);
        $arg  = $args[0] ?? null;

        if ($arg === null) {
            return null;
        }

        if ($scope !== null) {
            foreach ($this->typeHelper->constantStrings($scope->getType($arg)) as $class) {
                if ($this->reflectionProvider->hasClass($class)) {
                    return $this->reflectionProvider->getClass($class)->getName();
                }
            }

            return null;
        }

        if (! $arg instanceof ClassConstFetch || ! $arg->class instanceof Name || ! $arg->name instanceof Identifier || $arg->name->toString() !== 'class') {
            return null;
        }

        $class = $this->resolveName($arg->class, $inClass);

        return $this->reflectionProvider->hasClass($class) ? $class : null;
    }

    /** @return list<Type> */
    private function inValues(Expr $expr, ClassReflection|null $inClass, Scope|null $scope = null): array
    {
        $args = $this->ruleCallArgs($expr, $inClass, In::class, 'in', $scope);

        if ($args === []) {
            return [];
        }

        $values = [];

        foreach ($args as $arg) {
            if ($scope !== null) {
                foreach ($this->typeHelper->constantValues($scope->getType($arg)) as $value) {
                    $values[] = $value;
                }

                continue;
            }

            foreach ($this->constantScalars($arg) as $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param class-string $objectClass
     *
     * @return list<Expr>
     */
    private function ruleCallArgs(Expr $expr, ClassReflection|null $inClass, string $objectClass, string $staticMethod, Scope|null $scope = null): array
    {
        if ($scope !== null) {
            return $this->scopedRuleCallArgs($expr, $objectClass, $staticMethod, $scope);
        }

        if ($expr instanceof StaticCall) {
            if (! $expr->class instanceof Name || ! $expr->name instanceof Identifier) {
                return [];
            }

            if ($expr->name->toString() !== $staticMethod || ! $this->isClass($expr->class, $inClass, Rule::class)) {
                return [];
            }
        } elseif ($expr instanceof New_) {
            if (! $expr->class instanceof Name || ! $this->isClass($expr->class, $inClass, $objectClass)) {
                return [];
            }
        } else {
            return [];
        }

        return $this->callHelper->argValues($expr);
    }

    /**
     * @param class-string $objectClass
     *
     * @return list<Expr>
     */
    private function scopedRuleCallArgs(Expr $expr, string $objectClass, string $staticMethod, Scope $scope): array
    {
        if ($expr instanceof StaticCall) {
            if ($this->callHelper->matchingNames($expr, $scope, $staticMethod) === [] || ! $this->callHelper->isCalledOn($expr, $scope, Rule::class)) {
                return [];
            }

            return $this->callHelper->argValues($expr);
        }

        if (! $expr instanceof New_ || ! $this->callHelper->isCalledOn($expr, $scope, $objectClass)) {
            return [];
        }

        return $this->callHelper->argValues($expr);
    }

    private function isClass(Name $name, ClassReflection|null $inClass, string $class): bool
    {
        $resolved = $this->resolveName($name, $inClass);

        return $this->reflectionProvider->hasClass($resolved)
            && $this->reflectionProvider->getClass($resolved)->is($class);
    }

    /** @return list<Type> */
    private function constantScalars(Expr $expr): array
    {
        if ($expr instanceof String_) {
            return [new ConstantStringType($expr->value)];
        }

        if ($expr instanceof Int_) {
            return [new ConstantIntegerType($expr->value)];
        }

        if (! $expr instanceof Array_) {
            return [];
        }

        $values = [];

        foreach ($expr->items as $item) {
            foreach ($this->constantScalars($item->value) as $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function intParam(string|null $value): int|null
    {
        if ($value === null || $value === '' || ! is_numeric($value) || str_contains($value, '.')) {
            return null;
        }

        return (int) $value;
    }

    private function resolveName(Name $name, ClassReflection|null $inClass): string
    {
        if ($name->isFullyQualified()) {
            return $name->toString();
        }

        $resolved = $name->getAttribute('resolvedName');

        if ($resolved instanceof Name) {
            return $resolved->toString();
        }

        $short = $name->toString();

        if ($this->reflectionProvider->hasClass($short)) {
            return $this->reflectionProvider->getClass($short)->getName();
        }

        if ($inClass === null) {
            return $short;
        }

        $namespaced = $inClass->getNativeReflection()->getNamespaceName();
        $candidate  = $namespaced === '' ? $short : $namespaced . '\\' . $short;

        return $this->reflectionProvider->hasClass($candidate)
            ? $this->reflectionProvider->getClass($candidate)->getName()
            : $short;
    }

    /** @param array{names: list<string>, enum: string|null, min: int|null, max: int|null, in: list<Type>} $tokens */
    private function valueType(array $tokens): Type
    {
        $names = $tokens['names'];

        if (in_array('file', $names, true) || in_array('image', $names, true) || in_array('mimes', $names, true) || in_array('mimetypes', $names, true)) {
            return new ObjectType(UploadedFile::class);
        }

        if ($tokens['in'] !== []) {
            return TypeCombinator::union(...$tokens['in']);
        }

        if ($tokens['enum'] !== null) {
            return $this->enumBackingType($tokens['enum']);
        }

        if (in_array('integer', $names, true) || in_array('int', $names, true)) {
            $int = $tokens['min'] !== null || $tokens['max'] !== null
                ? IntegerRangeType::fromInterval($tokens['min'], $tokens['max'])
                : new IntegerType();

            return TypeCombinator::union(
                $int,
                TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType()),
            );
        }

        if (in_array('numeric', $names, true)) {
            return TypeCombinator::union(
                new IntegerType(),
                new FloatType(),
                TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType()),
            );
        }

        if (in_array('boolean', $names, true) || in_array('bool', $names, true)) {
            return TypeCombinator::union(new BooleanType(), new StringType());
        }

        if (in_array('array', $names, true) || in_array('list', $names, true)) {
            return new ArrayType(new IntegerType(), new StringType());
        }

        return new StringType();
    }

    private function enumBackingType(string $class): Type
    {
        if (! $this->reflectionProvider->hasClass($class)) {
            return new StringType();
        }

        $reflection = $this->reflectionProvider->getClass($class);

        if (! $reflection->isEnum()) {
            return new StringType();
        }

        $values = [];

        foreach ($reflection->getEnumCases() as $case) {
            $value = $case->getBackingValueType();

            if ($value === null) {
                continue;
            }

            $values[] = $value;

            foreach ($value->getConstantScalarValues() as $scalar) {
                if (! is_int($scalar)) {
                    continue;
                }

                $values[] = new ConstantStringType((string) $scalar);
            }
        }

        return $values === [] ? new StringType() : TypeCombinator::union(...$values);
    }

    /** @param  array<string, array{required: bool, nullable: bool, type: Type}> $fields */
    private function shape(array $fields): Type
    {
        $groups = [];

        foreach ($fields as $path => $field) {
            $parts           = explode('.', $path);
            $head            = array_shift($parts);
            $tail            = implode('.', $parts);
            $groups[$head][] = [$tail, $field];
        }

        if (array_key_exists('*', $groups) && count($groups) === 1) {
            [$type] = $this->groupType($groups['*']);

            return TypeCombinator::intersect(new ArrayType(new IntegerType(), $type), new AccessoryArrayListType());
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($groups as $key => $entries) {
            if ($key === '*') {
                continue;
            }

            [$type, $required] = $this->groupType($entries);
            $builder->setOffsetValueType(new ConstantStringType($key), $type, ! $required);
        }

        return $builder->getArray();
    }

    /**
     * @param  list<array{0: string, 1: array{required: bool, nullable: bool, type: Type}}> $entries
     *
     * @return array{0: Type, 1: bool}
     */
    private function groupType(array $entries): array
    {
        $nested   = [];
        $required = false;
        $type     = new StringType();
        $nullable = false;
        $hasLeaf  = false;

        foreach ($entries as [$tail, $field]) {
            if ($tail === '') {
                $hasLeaf  = true;
                $type     = $field['type'];
                $nullable = $field['nullable'];
                $required = $required || $field['required'];
                continue;
            }

            $nested[$tail] = $field;
            $required      = $required || $field['required'];
        }

        if ($nested !== []) {
            $type = $this->shape($nested);
        } elseif ($hasLeaf && $nullable) {
            $type = TypeCombinator::union($type, new NullType());
        }

        return [$type, $required];
    }

    /** @return array<string, Type> */
    private function topLevel(Type $shape): array
    {
        $properties = [];

        foreach ($shape->getConstantArrays() as $array) {
            foreach ($array->getKeyTypes() as $i => $key) {
                foreach ($key->getConstantStrings() as $string) {
                    $properties[$string->getValue()] = $array->getValueTypes()[$i];
                }
            }
        }

        return $properties;
    }

    private function stringExpr(Expr $expr): string|null
    {
        return $expr instanceof String_ ? $expr->value : null;
    }
}
