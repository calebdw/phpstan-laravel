<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use ArrayAccess;
use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\Scope;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\CallableType;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\UnionType;
use Throwable;
use UnitEnum;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function in_array;
use function is_int;
use function is_string;
use function strval;

final class ColumnHelper
{
    public function __construct(private TypeHelper $typeHelper)
    {
    }

    public function getArrayType(Type $from, Arg $valueArg, Arg|null $keyArg, Scope $scope): ArrayType
    {
        $valueType = $this->getTypeFromArg($from, $valueArg, $scope);
        $keyType   = $keyArg === null ? new IntegerType() : $this->getTypeFromArg($from, $keyArg, $scope);

        $keyType   ??= new BenevolentUnionType([new IntegerType(), new StringType()]);
        $valueType ??= new MixedType();

        return new ArrayType($this->normalizeKey($keyType), $valueType);
    }

    public function getCollectionType(Type $from, Arg $valueArg, Arg|null $keyArg, Scope $scope, string|null $collectionClass = null): GenericObjectType
    {
        $type = $this->getArrayType($from, $valueArg, $keyArg, $scope);

        return new GenericObjectType($collectionClass ?? Collection::class, [$type->getKeyType(), $type->getItemType()]);
    }

    /**
     * Resolves the key a column or callback produces, for keyBy, which
     * rewrites keys and leaves the values alone.
     */
    public function getKeyType(Type $from, Arg $keyArg, Scope $scope): Type
    {
        return $this->getTypeFromArg($from, $keyArg, $scope)
            ?? new BenevolentUnionType([new IntegerType(), new StringType()]);
    }

    /**
     * groupBy() needs one extra step: a grouper returning an array files its
     * item under each of that array's values.
     */
    public function normalizeGroupKey(Type $type): Type
    {
        if ($type->isArray()->yes()) {
            $type = $type->getIterableValueType();
        }

        return $this->normalizeKey($type);
    }

    /**
     * Casts a resolved key the way PHP does on the way into the array.
     *
     * A union is cast member by member, because a nullable column contributes
     * an empty string rather than a null, and null is not a key at all: left
     * alone it produces a TKey outside the array-key bound, which PHPStan
     * then reports as a type not matching itself.
     *
     * groupBy() and keyBy() differ in the framework only in that groupBy()
     * stringifies a Stringable and leaves any other object alone. That branch
     * is unreachable in code that is not already broken, since the stubs
     * restrict a grouper to int|string|Stringable|UnitEnum and a keyBy
     * callback to int|string, so keyBy()'s rule serves for both and avoids
     * propagating an object as a key type.
     */
    public function normalizeKey(Type $type): Type
    {
        if ($type instanceof BenevolentUnionType) {
            return $type;
        }

        if ($type instanceof UnionType) {
            return TypeCombinator::union(...array_map(
                fn (Type $member): Type => $this->castKey($member),
                $type->getTypes(),
            ));
        }

        return $this->castKey($type);
    }

    private function castKey(Type $type): Type
    {
        return match (true) {
            $type->isBoolean()->yes() => new IntegerType(),
            (new ObjectType(UnitEnum::class))->isSuperTypeOf($type)->yes()
                => new BenevolentUnionType([new IntegerType(), new StringType()]),
            $type->isNull()->yes() => new ConstantStringType(''),
            $type->isObject()->yes() => new StringType(),
            default => $type,
        };
    }

    /**
     * mapSpread() does $callback(...$chunk) after appending the key.
     * Only a known list of slots (a constant array / array shape) can be
     * spread into named parameters.
     *
     * @return list<Type>|null
     */
    public function spreadSlots(Type $chunkType, Type $keyType): array|null
    {
        $arrays = $chunkType->getConstantArrays();

        if ($arrays === []) {
            return null;
        }

        $slots = [];

        foreach ($arrays as $array) {
            foreach (array_values($array->getValueTypes()) as $i => $valueType) {
                $slots[$i] = isset($slots[$i])
                    ? TypeCombinator::union($slots[$i], $valueType)
                    : $valueType;
            }
        }

        if ($slots === []) {
            return null;
        }

        $slots[] = $keyType;

        return array_values($slots);
    }

    public function getTypeFromArg(Type $from, Arg $arg, Scope $scope): Type|null
    {
        $type = $scope->getType($arg->value);

        if ($type->isCallable()->yes()) {
            return $this->returnTypeFromCallable($arg->value, [$from], $scope);
        }

        $types = array_filter(array_map(
            fn ($key) => $this->pluckFromType($from, $key, $scope),
            $this->dataGetPaths($type) ?? [],
        ));

        return $types === [] ? null : TypeCombinator::union(...$types);
    }

    /** @param list<Type> $parameterTypes */
    public function returnTypeFromCallable(Expr $callable, array $parameterTypes, Scope $scope): Type|null
    {
        /** @phpstan-ignore phpstanApi.class */
        if (! $scope instanceof MutatingScope) {
            return null;
        }

        $parameters = array_map(static fn ($t) => new SimpleParameterReflection('param', $t), $parameterTypes);

        /** @phpstan-ignore phpstanApi.method */
        $scopeWithContext = $scope->pushInFunctionCall(
            null,
            new SimpleParameterReflection('callback', new CallableType($parameters, new MixedType())),
            false,
        );

        $callableType = $scopeWithContext->getType($callable);

        if ($callableType instanceof ClosureType) {
            return $callableType->getReturnType();
        }

        return null;
    }

    /**
     * Resolves a key against a type, as a property or as an offset, following
     * each segment of a dotted path in turn.
     *
     * @param array<int, string> $keys
     */
    public function pluckFromType(Type $from, array $keys, Scope $scope): Type|null
    {
        if ($keys === []) {
            return null;
        }

        foreach ($keys as $key) {
            if (! $from->hasInstanceProperty($key)->no()) {
                try {
                    $from = $from->getInstanceProperty($key, $scope)->getReadableType();

                    continue;
                } catch (Throwable) {
                }
            }

            $keyType = new ConstantStringType($key);

            if (! $from->hasOffsetValueType($keyType)->no()) {
                try {
                    $from = $from->getOffsetValueType($keyType);

                    continue;
                } catch (Throwable) {
                }
            }

            return null;
        }

        return $from;
    }

    /** Reads a key off a target the way data_get() does. */
    public function dataGet(Type $target, Type $key, Arg|null $default, Scope $scope): Type|null
    {
        if ($key->isNull()->yes()) {
            return $target;
        }

        $paths = $this->dataGetPaths(TypeCombinator::removeNull($key));

        if ($paths === null) {
            return null;
        }

        $default = $default === null ? new NullType() : $this->typeHelper->valueOf($scope->getType($default->value), $scope);
        $types   = $key->isNull()->no() ? [] : [$target];

        foreach ($paths as $segments) {
            $types[] = $this->dataGetPath($target, $segments, $default, $scope);
        }

        return TypeCombinator::union(...$types);
    }

    /**
     * data_get() splits a string or int key on dots and takes an array key as
     * the segments themselves. Null when a member of the key is not constant.
     *
     * @return list<list<string>>|null
     */
    private function dataGetPaths(Type $key): array|null
    {
        $paths = [];

        foreach (TypeUtils::flattenTypes($key) as $member) {
            if ($member->isConstantArray()->yes()) {
                foreach ($member->getConstantArrays() as $array) {
                    $arrayPaths = $this->arrayKeyPaths($array);

                    if ($arrayPaths === null) {
                        return null;
                    }

                    $paths = [...$paths, ...$arrayPaths];
                }

                continue;
            }

            $values = $this->segmentValues($member);

            if ($values === null) {
                return null;
            }

            foreach ($values as $value) {
                $paths[] = explode('.', $value);
            }
        }

        return $paths;
    }

    /** @return list<list<string>>|null */
    private function arrayKeyPaths(ConstantArrayType $array): array|null
    {
        $paths = [[]];

        foreach ($array->getValueTypes() as $valueType) {
            $values = $this->segmentValues($valueType);

            if ($values === null) {
                return null;
            }

            $next = [];

            foreach ($paths as $path) {
                foreach ($values as $value) {
                    $next[] = [...$path, $value];
                }
            }

            if (count($next) > ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT) {
                return null;
            }

            $paths = $next;
        }

        return $paths;
    }

    /** @return list<string>|null */
    private function segmentValues(Type $type): array|null
    {
        $values = $type->getConstantScalarValues();

        foreach ($values as $value) {
            if (! is_string($value) && ! is_int($value)) {
                return null;
            }
        }

        return $values === [] ? null : array_map(strval(...), $values);
    }

    /**
     * data_get() returns the default as soon as a segment is missing, so the
     * default is part of the type whenever a segment may be.
     *
     * @param list<string> $segments
     */
    private function dataGetPath(Type $target, array $segments, Type $default, Scope $scope): Type
    {
        $missing = false;

        foreach ($segments as $i => $segment) {
            if ($segment === '*') {
                $target = $this->dataGetWildcard($target, array_slice($segments, $i + 1), $default, $scope);

                break;
            }

            [$target, $segmentMissing] = $this->dataGetSegment($target, $segment, $scope);

            if ($target === null) {
                return $default;
            }

            $missing = $missing || $segmentMissing;
        }

        return $missing ? TypeCombinator::union($target, $default) : $target;
    }

    /**
     * data_get() reads each item without a default and returns the default only
     * for a target that is not iterable. Arr::collapse() merges the arrays and
     * skips everything else.
     *
     * @param list<string> $rest
     */
    private function dataGetWildcard(Type $target, array $rest, Type $default, Scope $scope): Type
    {
        if ($target->isIterable()->no()) {
            return $default;
        }

        $item = TypeCombinator::intersect($target, new IterableType(new MixedType(), new MixedType()))->getIterableValueType();

        if ($rest !== []) {
            $item = $this->dataGetPath($item, $rest, new NullType(), $scope);
        }

        if (in_array('*', $rest, true)) {
            $item = TypeCombinator::intersect($item, new ArrayType(new MixedType(), new MixedType()))->getIterableValueType();
        }

        $list = TypeCombinator::intersect(new ArrayType(new IntegerType(), $item), new AccessoryArrayListType());

        return $target->isIterable()->yes() ? $list : TypeCombinator::union($list, $default);
    }

    /**
     * data_get() reads an array or ArrayAccess offset with Arr::exists(), which
     * keeps a null value, and falls back to isset() on a property.
     *
     * @return array{0: Type|null, 1: bool}
     */
    private function dataGetSegment(Type $target, string $segment, Scope $scope): array
    {
        $offset  = (new ConstantStringType($segment))->toArrayKey();
        $values  = [];
        $missing = false;

        // TypeUtils::flattenTypes() would expand every optional-key combination of a shape.
        foreach ($target instanceof UnionType ? $target->getTypes() : [$target] as $member) {
            $hasOffset = $this->readsOffset($member) ? $member->hasOffsetValueType($offset) : TrinaryLogic::createNo();

            if (! $hasOffset->no()) {
                $values[] = $member->getOffsetValueType($offset);
                $missing  = $missing || ! $hasOffset->yes();

                continue;
            }

            $value = $member->isObject()->no() ? null : $this->pluckFromType($member, [$segment], $scope);

            if ($value === null) {
                $missing = true;

                continue;
            }

            $missing  = $missing || ! $member->hasInstanceProperty($segment)->yes() || ! $value->isNull()->no();
            $values[] = TypeCombinator::removeNull($value);
        }

        return [$values === [] ? null : TypeCombinator::union(...$values), $missing];
    }

    /** A model's offsetExists() is `! is_null()`, so a model reads like isset() on a property. */
    private function readsOffset(Type $member): bool
    {
        if ($this->typeHelper->isCalledOn($member, Model::class)) {
            return false;
        }

        return ! $member->isArray()->no() || $this->typeHelper->isCalledOn($member, ArrayAccess::class);
    }
}
