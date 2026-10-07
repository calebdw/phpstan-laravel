<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\ArrayRule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\ExcludeUnless;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
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
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\ErrorType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectShapeType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use Throwable;

use function array_intersect;
use function array_key_exists;
use function array_map;
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
use function str_starts_with;
use function strtolower;

/**
 * @phpstan-type Field array{
 *     required: bool,
 *     nullable: bool,
 *     type: Type,
 *     strippable: bool,
 *     excludable: bool
 * }
 * @phpstan-type Tokens array{
 *     names: list<string>,
 *     enum: string|null,
 *     min: int|null,
 *     max: int|null,
 *     in: list<Type>,
 *     strippable: bool,
 *     strict: bool
 * }
 * @phpstan-type Group array{type: Type, kept: bool, keepsParent: bool}
 * @phpstan-type Shape array{type: Type, anyKept: bool, allKeepParent: bool}
 */
final class ValidationHelper
{
    /** Implicit rules that fail when the key is absent. */
    private const array PRESENCE_RULES = ['required', 'present', 'accepted', 'declined'];

    /** `missing` fails when the key is present, so like `exclude` it leaves the key out. */
    private const array EXCLUDE_RULES = ['exclude', 'exclude_if', 'exclude_unless', 'exclude_with', 'exclude_without', 'missing'];

    /** @var array<string, Type|null> */
    private array $shapes = [];

    private bool $stripsUnvalidatedKeys;

    /** The rule objects that put an uploaded file on the field. */
    private Type $fileRules;

    /** The rule objects Laravel wraps instead of casting to a string. */
    private Type $wrappedRules;

    /** @var array<string, array<string, Type>> */
    private array $properties = [];

    public function __construct(
        private Parser $parser,
        private ReflectionProvider $reflectionProvider,
        private TypeHelper $typeHelper,
        private CallHelper $callHelper,
        private ContainerHelper $containerHelper,
    ) {
        $this->fileRules = TypeCombinator::union(
            new ObjectType(File::class),
            new ObjectType(Dimensions::class),
        );

        $this->wrappedRules = TypeCombinator::union(
            new ObjectType(Closure::class),
            new ObjectType(ValidationRule::class),
            // Deprecated, but user rules still implement them.
            new ObjectType('Illuminate\Contracts\Validation\Rule'),
            new ObjectType('Illuminate\Contracts\Validation\InvokableRule'),
        );
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

    /** @return array<string, Field> */
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

    /** @return array<string, Field> */
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

    /** @return Field */
    private function fieldFromRules(Expr $expr, ClassReflection|null $class, Scope|null $scope = null): array
    {
        $required   = true;
        $nullable   = false;
        $strippable = false;
        $excludable = false;
        $types      = [];

        foreach ($this->ruleBranches($expr) as $branch) {
            $tokens     = $this->ruleTokens($branch, $class, $scope);
            $required   = $required
                && array_intersect($tokens['names'], self::PRESENCE_RULES) !== []
                && ! in_array('sometimes', $tokens['names'], true);
            $nullable   = $nullable || in_array('nullable', $tokens['names'], true);
            $strippable = $strippable || $tokens['strippable'];
            $excludable = $excludable || array_intersect($tokens['names'], self::EXCLUDE_RULES) !== [];

            // `exclude` and `missing` always drop the key; a conditional exclude keeps its type.
            if (array_intersect($tokens['names'], ['exclude', 'missing']) !== []) {
                continue;
            }

            $types[] = $this->valueType($tokens);
        }

        return [
            'required' => $required,
            'nullable' => $nullable,
            'type' => $types === [] ? new MixedType() : TypeCombinator::union(...$types),
            'strippable' => $strippable && $this->stripsUnvalidatedKeys(),
            'excludable' => $excludable,
        ];
    }

    /**
     * Whether validated() drops an array parent that has child rules.
     *
     * An application can turn this off with includeUnvalidatedArrayKeys(), and
     * the booted container answers for the one being analysed. A validator the
     * container cannot build answers with Laravel's own default.
     */
    private function stripsUnvalidatedKeys(): bool
    {
        if (isset($this->stripsUnvalidatedKeys)) {
            return $this->stripsUnvalidatedKeys;
        }

        $factory = $this->containerHelper->resolve(ValidationFactory::class);

        try {
            $validator = $factory instanceof ValidationFactory ? $factory->make([], []) : null;
        } catch (Throwable) {
            $validator = null;
        }

        return $this->stripsUnvalidatedKeys = ! $validator instanceof Validator
            || $validator->excludeUnvalidatedArrayKeys;
    }

    /**
     * Splits every ternary, whole or inside a rule array, so that each branch
     * is one list of rules.
     *
     * @return list<list<Expr>>
     */
    private function ruleBranches(Expr $expr): array
    {
        if ($expr instanceof Ternary) {
            return [
                ...$this->ruleBranches($expr->if ?? $expr->cond),
                ...$this->ruleBranches($expr->else),
            ];
        }

        if (! $expr instanceof Array_) {
            return [[$expr]];
        }

        $branches = [[]];

        foreach ($expr->items as $item) {
            $values = $item->value instanceof Ternary ? $this->ruleBranches($item->value) : [[$item->value]];
            $next   = [];

            foreach ($branches as $branch) {
                foreach ($values as $value) {
                    $next[] = [...$branch, ...$value];
                }
            }

            $branches = $next;
        }

        return $branches;
    }

    /**
     * @param list<Expr> $rules
     *
     * @return Tokens
     */
    private function ruleTokens(array $rules, ClassReflection|null $class, Scope|null $scope = null): array
    {
        $names      = [];
        $enum       = null;
        $min        = null;
        $max        = null;
        $in         = [];
        $strippable = false;
        $strict     = false;

        foreach ($rules as $rule) {
            if ($rule instanceof String_) {
                foreach (explode('|', $rule->value) as $token) {
                    [$name, $arg] = array_pad(explode(':', $token, 2), 2, null);
                    $name         = strtolower((string) $name);
                    $names[]      = $name;
                    $strippable   = $strippable || ($arg === null && in_array($name, ['array', 'list'], true));

                    if ($name === 'enum' && is_string($arg) && $arg !== '' && $this->reflectionProvider->hasClass($arg)) {
                        $enum = $this->reflectionProvider->getClass($arg)->getName();
                    }

                    if ($name === 'min') {
                        $min = $this->intParam($arg);
                    }

                    if ($name === 'max') {
                        $max = $this->intParam($arg);
                    }

                    if (($name === 'boolean' || $name === 'bool') && $arg === 'strict') {
                        $strict = true;
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

            if ($rule instanceof FunctionLike) {
                continue;
            }

            $root      = $this->chainRoot($rule);
            $enumClass = $this->enumClass($root, $class, $scope);

            if ($enumClass !== null) {
                $names[] = 'enum';
                $enum    = $enumClass;

                continue;
            }

            $values = $this->inValues($root, $class, $scope);

            foreach ($values as $value) {
                $names[] = 'in';
                $in[]    = $value;
            }

            if ($this->ruleCall($rule, $class, ExcludeIf::class, 'excludeIf', $scope) !== null) {
                $names[] = 'exclude_if';
            }

            if ($this->ruleCall($rule, $class, ExcludeUnless::class, 'excludeUnless', $scope) !== null) {
                $names[] = 'exclude_unless';
            }

            $type       = $scope?->getType($rule) ?? $this->unscopedRuleType($root, $class);
            $strippable = $strippable || $this->castsToArray($rule, $type, $class, $scope);

            if ($values !== [] || ! $this->isFileRule($type)) {
                continue;
            }

            $names[] = 'file';
        }

        return [
            'names' => $names,
            'enum' => $enum,
            'min' => $min,
            'max' => $max,
            'in' => $in,
            'strippable' => $strippable,
            'strict' => $strict,
        ];
    }

    private function chainRoot(Expr $expr): Expr
    {
        while ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall) {
            $expr = $expr->var;
        }

        return $expr;
    }

    private function isFileRule(Type|null $type): bool
    {
        return $type !== null && $this->fileRules->isSuperTypeOf($type)->yes();
    }

    /**
     * Laravel declares no native return types on its rule builders, so a
     * static call is read through its PHPDoc.
     */
    private function unscopedRuleType(Expr $root, ClassReflection|null $inClass): Type|null
    {
        if ((! $root instanceof StaticCall && ! $root instanceof New_) || ! $root->class instanceof Name) {
            return null;
        }

        $class = $this->resolveName($root->class, $inClass);

        if (! $this->reflectionProvider->hasClass($class)) {
            return null;
        }

        if ($root instanceof New_) {
            return new ObjectType($class);
        }

        $reflection = $this->reflectionProvider->getClass($class);

        if (! $root->name instanceof Identifier || ! $reflection->hasNativeMethod($root->name->toString())) {
            return null;
        }

        return ParametersAcceptorSelector::selectFromTypes(
            array_map(static fn () => new MixedType(), $root->getArgs()),
            $reflection->getNativeMethod($root->name->toString())->getVariants(),
            false,
        )->getReturnType();
    }

    /**
     * validated() drops a parent whose rules hold an exact `array` or `list`.
     *
     * Laravel wraps a closure and a rule contract, and casts every other rule
     * object to a string, so a rule object strips the parent only when that
     * string is exactly `array`. A rule the parser cannot place may be.
     */
    private function castsToArray(Expr $rule, Type|null $type, ClassReflection|null $class, Scope|null $scope): bool
    {
        $args = $this->ruleCall($rule, $class, ArrayRule::class, 'array', $scope);

        if ($args !== null) {
            return $this->withoutKeys($args, $scope);
        }

        if ($type === null) {
            return true;
        }

        return ! $this->wrappedRules->isSuperTypeOf($type)->yes() && ! $this->isRuleBuilder($type);
    }

    /**
     * Every builder Laravel ships casts to its own rule name, so none of them
     * reads as `array`. `Rule::array()` is the exception, and only the call
     * that built it says whether it carries keys.
     */
    private function isRuleBuilder(Type $type): bool
    {
        $names = $type->getObjectClassNames();

        if ($names === []) {
            return false;
        }

        foreach ($names as $name) {
            if ($name === ArrayRule::class || ! str_starts_with($name, 'Illuminate\\Validation\\Rules\\')) {
                return false;
            }
        }

        return true;
    }

    /**
     * `Rule::array()` casts to a bare `array` only when its keys come from an
     * empty array; any other argument becomes a key.
     *
     * @param list<Expr> $args
     */
    private function withoutKeys(array $args, Scope|null $scope): bool
    {
        if (count($args) !== 1) {
            return $args === [];
        }

        if ($scope === null) {
            return $args[0] instanceof Array_ ? $args[0]->items === [] : ! $args[0] instanceof Scalar;
        }

        $type = $scope->getType($args[0]);

        return ! $type->isIterableAtLeastOnce()->yes() && ! $type->isScalar()->yes() && ! $type->isNull()->yes();
    }

    private function enumClass(Expr $expr, ClassReflection|null $inClass, Scope|null $scope = null): string|null
    {
        $arg = $this->ruleCall($expr, $inClass, Enum::class, 'enum', $scope)[0] ?? null;

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
        $args = $this->ruleCall($expr, $inClass, In::class, 'in', $scope) ?? [];

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
     * The arguments of `Rule::$staticMethod()` or `new $objectClass()`, or
     * null when the expression builds neither.
     *
     * @param class-string $objectClass
     *
     * @return list<Expr>|null
     */
    private function ruleCall(Expr $expr, ClassReflection|null $inClass, string $objectClass, string $staticMethod, Scope|null $scope = null): array|null
    {
        if ($scope !== null) {
            return $this->scopedRuleCall($expr, $objectClass, $staticMethod, $scope);
        }

        if ($expr instanceof StaticCall) {
            if (! $expr->class instanceof Name || ! $expr->name instanceof Identifier) {
                return null;
            }

            if ($expr->name->toString() !== $staticMethod || ! $this->isClass($expr->class, $inClass, Rule::class)) {
                return null;
            }
        } elseif ($expr instanceof New_) {
            if (! $expr->class instanceof Name || ! $this->isClass($expr->class, $inClass, $objectClass)) {
                return null;
            }
        } else {
            return null;
        }

        return $this->callHelper->argValues($expr);
    }

    /**
     * @param class-string $objectClass
     *
     * @return list<Expr>|null
     */
    private function scopedRuleCall(Expr $expr, string $objectClass, string $staticMethod, Scope $scope): array|null
    {
        if ($expr instanceof StaticCall) {
            if ($this->callHelper->matchingNames($expr, $scope, $staticMethod) === [] || ! $this->callHelper->isCalledOn($expr, $scope, Rule::class)) {
                return null;
            }

            return $this->callHelper->argValues($expr);
        }

        if (! $expr instanceof New_ || ! $this->callHelper->isCalledOn($expr, $scope, $objectClass)) {
            return null;
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

    /** @param Tokens $tokens */
    private function valueType(array $tokens): Type
    {
        $names = $tokens['names'];

        if (array_intersect($names, ['file', 'image', 'mimes', 'mimetypes', 'extensions', 'dimensions']) !== []) {
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

        $booleans = $this->booleanValues($tokens);

        if ($booleans !== null) {
            return $booleans;
        }

        if (in_array('list', $names, true)) {
            return TypeCombinator::intersect(
                new ArrayType(new IntegerType(), new MixedType()),
                new AccessoryArrayListType(),
            );
        }

        if (in_array('array', $names, true)) {
            return new ArrayType(
                TypeCombinator::union(new IntegerType(), new StringType()),
                new MixedType(),
            );
        }

        return new StringType();
    }

    /** @param Tokens $tokens */
    private function booleanValues(array $tokens): Type|null
    {
        $names = $tokens['names'];
        $sets  = [];

        if (in_array('boolean', $names, true) || in_array('bool', $names, true)) {
            $sets[] = $tokens['strict'] ? new BooleanType() : $this->constants([true, false, 0, 1, '0', '1']);
        }

        if (in_array('accepted', $names, true)) {
            $sets[] = $this->constants([true, 1, '1', 'yes', 'on', 'true']);
        }

        if (in_array('declined', $names, true)) {
            $sets[] = $this->constants([false, 0, '0', 'no', 'off', 'false']);
        }

        return $sets === [] ? null : TypeCombinator::intersect(...$sets);
    }

    /** @param list<bool|int|string> $values */
    private function constants(array $values): Type
    {
        return TypeCombinator::union(...array_map(ConstantTypeHelper::getTypeFromValue(...), $values));
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

    /** @param  array<array-key, Field> $fields */
    private function shape(array $fields): Type
    {
        return $this->shapeOf($fields)['type'];
    }

    /**
     * @param  array<array-key, Field> $fields
     *
     * @return Shape
     */
    private function shapeOf(array $fields): array
    {
        /** @var array<array-key, list<array{0: string, 1: Field}>> $groups */
        $groups = [];

        foreach ($fields as $path => $field) {
            $parts           = explode('.', (string) $path);
            $head            = array_shift($parts);
            $tail            = implode('.', $parts);
            $groups[$head][] = [$tail, $field];
        }

        $allKeepParent = true;

        if (array_key_exists('*', $groups)) {
            $element       = $this->groupType($groups['*'], true);
            $allKeepParent = $element['keepsParent'];

            if (count($groups) === 1) {
                return [
                    'type' => new ArrayType(
                        TypeCombinator::union(new IntegerType(), new StringType()),
                        $element['type'],
                    ),
                    'anyKept' => false,
                    'allKeepParent' => $allKeepParent,
                ];
            }
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();
        $anyKept = false;

        foreach ($groups as $key => $entries) {
            if ($key === '*') {
                continue;
            }

            $group         = $this->groupType($entries);
            $anyKept       = $anyKept || $group['kept'];
            $allKeepParent = $allKeepParent && $group['keepsParent'];
            $keyType       = is_int($key)
                ? new ConstantIntegerType($key)
                : new ConstantStringType($key);
            $builder->setOffsetValueType($keyType, $group['type'], ! $group['kept']);
        }

        return ['type' => $builder->getArray(), 'anyKept' => $anyKept, 'allKeepParent' => $allKeepParent];
    }

    /**
     * validated() keeps a key whose own rule is not stripped, and rebuilds a
     * stripped parent from the children it keeps. A child that applies a rule
     * without being kept therefore loses the parent: a named rule applies to
     * every parent, a `*` rule only to elements that exist, and an exclusion
     * removes its rules along with the key.
     *
     * @param  list<array{0: string, 1: Field}> $entries
     *
     * @return Group
     */
    private function groupType(array $entries, bool $wildcard = false): array
    {
        $nested = [];
        $leaf   = null;

        foreach ($entries as [$tail, $field]) {
            if ($tail === '') {
                $leaf = $field;
                continue;
            }

            $nested[$tail] = $field;
        }

        if ($leaf === null) {
            $shape = $this->shapeOf($nested);

            return [
                'type' => $shape['type'],
                'kept' => $shape['anyKept'],
                'keepsParent' => $shape['anyKept'] || $shape['allKeepParent'],
            ];
        }

        $shape = $nested === []
            ? ['type' => $leaf['type'], 'anyKept' => false, 'allKeepParent' => true]
            : $this->shapeOf($nested);
        $type  = $shape['type'];

        if ($nested !== [] && $leaf['type']->isList()->yes()) {
            $type = TypeCombinator::intersect($type, new AccessoryArrayListType());
        }

        if ($leaf['nullable']) {
            $type = TypeCombinator::union($type, new NullType());
        }

        // A stripped key is rebuilt from the children it keeps, so it survives
        // only when one of them is kept, or when every child that is not kept
        // took its rule with it and left the parent whole.
        $keptWhenPresent = $shape['anyKept'] || ! $leaf['strippable'] || $shape['allKeepParent'];
        $kept            = $shape['anyKept'] || ($leaf['required'] && $keptWhenPresent);

        return [
            'type' => $type,
            'kept' => $kept && ! $leaf['excludable'],
            // A `*` entry says nothing about whether its parent was sent.
            'keepsParent' => $wildcard ? $keptWhenPresent : $kept,
        ];
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
