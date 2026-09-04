<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use Illuminate\Support\Collection;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\CallableType;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Throwable;
use UnitEnum;

use function array_filter;
use function array_map;
use function collect;
use function explode;

final class ColumnHelper
{
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

    public function getTypeFromArg(Type $from, Arg $arg, Scope $scope): Type|null
    {
        $type = $scope->getType($arg->value);

        if ($type->isCallable()->yes()) {
            return $this->returnTypeFromCallable($arg->value, [$from], $scope);
        }

        $values = $this->getKeysFromType($type);

        if ($values === []) {
            return null;
        }

        $types = array_filter(array_map(
            fn ($key) => $this->pluckFromType($from, $key, $scope),
            $values,
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

    /** @return array<int, array<int, string>> */
    private function getKeysFromType(Type $type): array
    {
        if ($type->isConstantArray()->yes()) {
            return collect($type->getConstantArrays())
                ->map(
                    static fn ($a) => collect($a->getValueTypes())
                        ->map(static fn ($t) => $t->getConstantStrings()[0] ?? null)
                        ->filter()
                        ->map(static fn ($s) => $s->getValue())
                        ->all() ?: null,
                )
                ->filter()
                ->all();
        }

        return collect($type->getConstantStrings())
            ->map(static fn ($s) => explode('.', $s->getValue()))
            ->all();
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
}
