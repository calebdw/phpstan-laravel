<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Support\Collection;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Reflection\Php\DummyParameter;
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
use Stringable;
use Throwable;
use UnitEnum;

use function array_filter;
use function array_map;
use function collect;
use function explode;

/** @internal */
final class ColumnHelper
{
    public function getArrayType(
        Type $from,
        Arg $valueArg,
        Arg|null $keyArg,
        Scope $scope,
    ): ArrayType {
        $valueType = $this->getTypeFromArg($from, $valueArg, $scope);
        $keyType   = $keyArg === null ? new IntegerType() : $this->getTypeFromArg($from, $keyArg, $scope);

        $keyType   ??= new BenevolentUnionType([new IntegerType(), new StringType()]);
        $valueType ??= new MixedType();

        return new ArrayType($keyType, $valueType);
    }

    public function getCollectionType(
        Type $from,
        Arg $valueArg,
        Arg|null $keyArg,
        Scope $scope,
        string|null $collectionClass = null,
    ): GenericObjectType {
        $type = $this->getArrayType($from, $valueArg, $keyArg, $scope);

        return new GenericObjectType(
            $collectionClass ?? Collection::class,
            [$type->getKeyType(), $type->getItemType()],
        );
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
     * groupBy() casts a group key before using it as an array key: a bool
     * becomes an int, an enum goes through enum_value(), and a Stringable or
     * null is stringified. A grouper returning an array files its item under
     * each of that array's values.
     */
    public function normalizeGroupKey(Type $type): Type
    {
        if ($type->isArray()->yes()) {
            $type = $type->getIterableValueType();
        }

        return match (true) {
            $type->isBoolean()->yes() => new IntegerType(),
            (new ObjectType(UnitEnum::class))->isSuperTypeOf($type)->yes()
                => new BenevolentUnionType([new IntegerType(), new StringType()]),
            $type->isNull()->yes() => new StringType(),
            (new ObjectType(Stringable::class))->isSuperTypeOf($type)->yes() => new StringType(),
            default => $type,
        };
    }

    /**
     * keyBy() casts differently to groupBy(): every object is stringified
     * rather than only Stringable ones. A bool is left alone there, but PHP
     * turns it into an int on the way into the array either way.
     */
    public function normalizeKey(Type $type): Type
    {
        return match (true) {
            $type->isBoolean()->yes() => new IntegerType(),
            (new ObjectType(UnitEnum::class))->isSuperTypeOf($type)->yes()
                => new BenevolentUnionType([new IntegerType(), new StringType()]),
            $type->isNull()->yes() => new StringType(),
            $type->isObject()->yes() => new StringType(),
            default => $type,
        };
    }

    private function getTypeFromArg(Type $from, Arg $arg, Scope $scope): Type|null
    {
        $type = $scope->getType($arg->value);

        if ($type->isCallable()->yes()) {
            return $this->getTypeFromCallable($arg->value, $from, $scope);
        }

        $values = $this->getKeysFromType($type);

        if ($values === []) {
            return null;
        }

        $types = array_filter(array_map(
            fn ($key) => $this->pluckFromType($from, $key, $scope),
            $values,
        ));

        return TypeCombinator::union(...$types);
    }

    private function getTypeFromCallable(Expr $callable, Type $parameterType, Scope $scope): Type|null
    {
        /** @phpstan-ignore phpstanApi.class */
        if (! $scope instanceof MutatingScope) {
            return null;
        }

        /** @phpstan-ignore phpstanApi.method, phpstanApi.constructor */
        $scopeWithContext = $scope->pushInFunctionCall(null, new DummyParameter(
            'callback',
            new CallableType([
                /** @phpstan-ignore phpstanApi.constructor */
                new NativeParameterReflection(
                    'param',
                    false,
                    $parameterType,
                    PassedByReference::createNo(),
                    false,
                    null,
                ),
            ], new MixedType()),
            false,
            PassedByReference::createNo(),
            false,
            null,
        ), false);

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

    /** @param array<int, string> $keys */
    private function pluckFromType(Type $from, array $keys, Scope $scope): Type|null
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
