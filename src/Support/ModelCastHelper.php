<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Stringable as IlluminateStringable;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use stdClass;
use Stringable;

use function array_combine;
use function array_key_exists;
use function array_map;
use function array_merge;
use function class_exists;
use function explode;
use function str_replace;

class ModelCastHelper
{
    /** @var array<string, array<string, string>> */
    private array $modelCasts = [];

    public function __construct(
        protected ReflectionProvider $reflectionProvider,
        protected ModelHelper $modelHelper,
    ) {
    }

    public function getReadableType(string $cast, Type $originalType): Type
    {
        $cast = $this->parseCast($cast);

        $attributeType = match ($cast) {
            'int', 'integer', 'timestamp' => new IntegerType(),
            'real', 'float', 'double' => new FloatType(),
            'decimal' => TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType()),
            'string' => new StringType(),
            'bool', 'boolean' => new BooleanType(),
            'object' => new ObjectType(stdClass::class),
            'array', 'json' => new ArrayType(new BenevolentUnionType([new IntegerType(), new StringType()]), new MixedType()),
            'collection' => new ObjectType(Collection::class),
            'date', 'datetime' => $this->getDateType(),
            'immutable_date', 'immutable_datetime' => new ObjectType(CarbonImmutable::class),
            AsArrayObject::class, AsEncryptedArrayObject::class => new ObjectType(ArrayObject::class),
            AsCollection::class, AsEncryptedCollection::class => new BenevolentUnionType([
                new GenericObjectType(Collection::class, [new BenevolentUnionType([new IntegerType(), new StringType()]), new MixedType()]),
                new NullType(),
            ]),
            AsStringable::class => new ObjectType(IlluminateStringable::class),
            default => null,
        };

        if ($attributeType) {
            return $attributeType;
        }

        if (! $this->reflectionProvider->hasClass($cast)) {
            return $originalType;
        }

        $classReflection = $this->reflectionProvider->getClass($cast);

        if ($classReflection->isEnum()) {
            return new ObjectType($cast);
        }

        if ($classReflection->is(Castable::class)) {
            $types = [];

            foreach ($classReflection->getNativeMethod('castUsing')->getVariants()[0]->getReturnType()->getObjectClassReflections() as $caster) {
                if ($caster->is(CastsAttributes::class)) {
                    $types[] = $caster->getNativeMethod('get')->getVariants()[0]->getReturnType();
                } elseif ($caster->is(CastsInboundAttributes::class)) {
                    $types[] = $originalType;
                }
            }

            if ($types !== []) {
                return TypeCombinator::union(...$types);
            }
        }

        if ($classReflection->is(CastsAttributes::class)) {
            return $classReflection->getNativeMethod('get')->getVariants()[0]->getReturnType();
        }

        if ($classReflection->is(CastsInboundAttributes::class)) {
            return $originalType;
        }

        return new MixedType();
    }

    public function getWriteableType(string $cast, Type $originalType): Type
    {
        $cast = $this->parseCast($cast);

        $attributeType = match ($cast) {
            'int', 'integer', 'timestamp' => new IntegerType(),
            'real', 'float', 'double' => new FloatType(),
            'decimal' => TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType(), new FloatType()),
            'string' => new StringType(),
            'bool', 'boolean' => TypeCombinator::union(new BooleanType(), new ConstantIntegerType(0), new ConstantIntegerType(1)),
            'object' => new ObjectType(stdClass::class),
            'array', 'json' => new ArrayType(new BenevolentUnionType([new IntegerType(), new StringType()]), new MixedType()),
            'collection' => new ObjectType(Collection::class),
            'date', 'datetime' => $this->getDateType(),
            'immutable_date', 'immutable_datetime' => new ObjectType(CarbonImmutable::class),
            AsArrayObject::class, AsCollection::class,
            AsEncryptedArrayObject::class, AsEncryptedCollection::class => new MixedType(),
            AsStringable::class => TypeCombinator::union(new StringType(), new ObjectType(Stringable::class)),
            default => null,
        };

        if ($attributeType) {
            return $attributeType;
        }

        if (! $this->reflectionProvider->hasClass($cast)) {
            return $originalType;
        }

        $classReflection = $this->reflectionProvider->getClass($cast);

        if ($classReflection->isEnum()) {
            return new ObjectType($cast);
        }

        if ($classReflection->is(Castable::class)) {
            $types = [];

            foreach ($classReflection->getNativeMethod('castUsing')->getVariants()[0]->getReturnType()->getObjectClassReflections() as $caster) {
                if (! $caster->is(CastsAttributes::class) && ! $caster->is(CastsInboundAttributes::class)) {
                    continue;
                }

                $valueParameter = Arr::first(
                    $caster->getNativeMethod('set')->getVariants()[0]->getParameters(),
                    static fn (ParameterReflection $parameterReflection) => $parameterReflection->getName() === 'value',
                );

                if ($valueParameter === null) {
                    continue;
                }

                $types[] = $valueParameter->getType();
            }

            if ($types !== []) {
                return TypeCombinator::union(...$types);
            }
        }

        if (
            $classReflection->is(CastsAttributes::class)
            || $classReflection->is(CastsInboundAttributes::class)
        ) {
            $valueParameter = Arr::first(
                $classReflection->getNativeMethod('set')->getVariants()[0]->getParameters(),
                static fn (ParameterReflection $parameterReflection) => $parameterReflection->getName() === 'value',
            );

            if ($valueParameter !== null) {
                return $valueParameter->getType();
            }
        }

        return new MixedType();
    }

    public function getDateType(): Type
    {
        $dateClass = class_exists(Date::class)
            ? Date::now()::class
            : IlluminateCarbon::class;

        if ($dateClass === IlluminateCarbon::class) {
            return new ObjectType(Carbon::class);
        }

        return new ObjectType($dateClass);
    }

    private function parseCast(string $cast): string
    {
        foreach (explode(':', $cast) as $part) {
            // If the cast is prefixed with `encrypted:` we need to skip to the next
            if ($part === 'encrypted') {
                continue;
            }

            return $part;
        }

        return $cast;
    }

    public function hasCastForProperty(ClassReflection $modelClassReflection, string $propertyName): bool
    {
        $modelCasts = $this->getModelCasts($modelClassReflection);

        return array_key_exists($propertyName, $modelCasts);
    }

    public function getCastForProperty(ClassReflection $modelClassReflection, string $propertyName): string|null
    {
        $modelCasts = $this->getModelCasts($modelClassReflection);

        return $modelCasts[$propertyName] ?? null;
    }

    /**
     * @return array<string, string>
     *
     * @throws ShouldNotHappenException
     * @throws MissingMethodFromReflectionException
     */
    private function getModelCasts(ClassReflection $modelClassReflection): array
    {
        $className = $modelClassReflection->getName();

        if (array_key_exists($className, $this->modelCasts)) {
            return $this->modelCasts[$className];
        }

        $modelInstance = $this->modelHelper->getModelInstance($modelClassReflection);
        $modelCasts    = $modelInstance?->getCasts() ?? [];

        $castsMethodReturnType = $modelClassReflection->getMethod(
            'casts',
            new OutOfClassScope(),
        )->getVariants()[0]->getReturnType();

        if ($castsMethodReturnType->isConstantArray()->yes()) {
            $modelCasts = array_merge(
                $modelCasts,
                array_combine(
                    /** @phpstan-ignore method.notFound (guarded by isConstantArray() above) */
                    array_map(static fn ($key) => $key->getValue(), $castsMethodReturnType->getKeyTypes()),
                    array_map(static function (Type $value) {
                        if ($value->isConstantValue()->yes()) {
                            /** @phpstan-ignore method.notFound (guarded by isConstantValue() above) */
                            return str_replace('\\\\', '\\', (string) $value->getValue());
                        }

                        return $value->describe(VerbosityLevel::value());
                    /** @phpstan-ignore method.notFound (guarded by isConstantArray() above) */
                    }, $castsMethodReturnType->getValueTypes()),
                ),
            );
        }

        $this->modelCasts[$className] = $modelCasts;

        return $modelCasts;
    }
}
