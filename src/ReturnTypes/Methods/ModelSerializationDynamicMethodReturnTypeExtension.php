<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ModelCastHelper;
use CalebDW\PhpstanLaravel\Support\ModelPropertyHelper;
use CalebDW\PhpstanLaravel\Support\ReflectionHelper;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\UnionType;

use function array_filter;
use function array_flip;
use function array_merge;
use function array_unique;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function lcfirst;
use function str_contains;

final class ModelSerializationDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    private const array DATE_CASTS = [
        'date'               => 1,
        'datetime'           => 1,
        'immutable_date'     => 1,
        'immutable_datetime' => 1,
    ];

    public function __construct(
        private ModelPropertyHelper $properties,
        private ModelCastHelper $casts,
        private ReflectionProvider $reflectionProvider,
        private ReflectionHelper $reflectionHelper,
    ) {
    }

    public function getClass(): string
    {
        return Model::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['toArray', 'attributesToArray'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $types = [];

        foreach ($scope->getType($methodCall->var)->getObjectClassReflections() as $class) {
            if (! $class->is(Model::class) || $class->isAbstract() || $class->getName() === Model::class) {
                continue;
            }

            foreach ([$methodReflection->getName(), 'attributesToArray', 'getAppends', 'getHidden', 'getVisible'] as $method) {
                if ($class->getNativeMethod($method)->getDeclaringClass()->getName() !== Model::class) {
                    continue 2;
                }
            }

            $appends = $this->configuredNames($class, 'Appends');
            $hidden  = array_flip($this->configuredNames($class, 'Hidden'));
            $visible = array_flip($this->configuredNames($class, 'Visible'));
            $names   = array_unique(array_merge($this->properties->getDatabasePropertyNames($class), $appends));

            if ($names === []) {
                continue;
            }

            $array = ConstantArrayTypeBuilder::createEmpty();

            foreach ($names as $name) {
                if (isset($hidden[$name]) || ($visible !== [] && ! isset($visible[$name]))) {
                    continue;
                }

                $array->setOffsetValueType(new ConstantStringType($name), $this->attributeType($class, $name, $scope), true);
            }

            $array->makeUnsealed(new StringType(), new MixedType());
            $types[] = $array->getArray();
        }

        return $types === [] ? null : TypeCombinator::union(...$types);
    }

    /** @return array<string> */
    private function configuredNames(ClassReflection $class, string $attribute): array
    {
        $defaults = $class->getNativeReflection()->getProperty(lcfirst($attribute))->getDefaultValue();
        $defaults = is_array($defaults) ? array_filter($defaults, is_string(...)) : [];

        return array_unique(array_merge(
            $defaults,
            $this->reflectionHelper->attributeStringArguments(
                $class,
                'Illuminate\\Database\\Eloquent\\Attributes\\' . $attribute,
            ),
        ));
    }

    private function attributeType(ClassReflection $class, string $name, Scope $scope): Type
    {
        $accessor = $this->properties->hasAccessor($class, $name);

        if ($accessor) {
            $type = $this->properties->getAccessor($class, $name)->getReadableType();
        } elseif ($this->properties->hasDatabaseProperty($class, $name)) {
            $type = $this->properties->getDatabaseProperty($class, $name)->getReadableType();
        } else {
            return new MixedType();
        }

        $cast           = $this->casts->getCastForProperty($class, $name);
        $castClass      = explode(':', $cast ?? '')[0];
        $castReflection = $this->reflectionProvider->hasClass($castClass) ? $this->reflectionProvider->getClass($castClass) : null;
        $classCast      = $castReflection !== null && ! $castReflection->isEnum() && ! $castReflection->is(CastsInboundAttributes::class);
        $enumCast       = ! $accessor && $castReflection !== null && $castReflection->isEnum();
        $date           = ! $accessor || $class->hasNativeMethod(Str::camel($name));

        if ($accessor) {
            if ($classCast && $cast !== null) {
                $type = $this->casts->getReadableType($cast, new MixedType());
                $date = false;
            }

            $cast = null;
        } elseif ($classCast) {
            if (in_array($castClass, [AsArrayObject::class, AsEncryptedArrayObject::class], true)) {
                return TypeCombinator::addNull(new ArrayType(TypeCombinator::union(new IntegerType(), new StringType()), new MixedType()));
            }

            if ($castReflection->is(Castable::class) && ! in_array($castClass, [AsCollection::class, AsEncryptedCollection::class, AsStringable::class], true)) {
                return new MixedType();
            }

            if ($castReflection->hasNativeMethod('serialize')) {
                $type = $castReflection->getNativeMethod('serialize')->getVariants()[0]->getReturnType();
                $date = false;
            }
        }

        return TypeTraverser::map($type, static function (Type $type, callable $traverse) use ($class, $scope, $cast, $date, $enumCast): Type {
            if ($type instanceof UnionType) {
                return $traverse($type);
            }

            if ($date && (new ObjectType(DateTimeInterface::class))->isSuperTypeOf($type)->yes()) {
                if ($cast !== null && str_contains($cast, ':') && isset(self::DATE_CASTS[explode(':', $cast, 2)[0]])) {
                    return new StringType();
                }

                return $class->getNativeMethod('serializeDate')->getVariants()[0]->getReturnType();
            }

            if ((new ObjectType(Enumerable::class))->isSuperTypeOf($type)->yes()) {
                $value = TypeTraverser::map($type->getIterableValueType(), static function (Type $item, callable $traverse) use ($scope): Type {
                    if ($item instanceof UnionType) {
                        return $traverse($item);
                    }

                    return (new ObjectType(Arrayable::class))->isSuperTypeOf($item)->yes()
                        ? $item->getMethod('toArray', $scope)->getVariants()[0]->getReturnType()
                        : $item;
                });

                return new ArrayType($type->getIterableKeyType(), $value);
            }

            if ((new ObjectType(Arrayable::class))->isSuperTypeOf($type)->yes()) {
                return $type->getMethod('toArray', $scope)->getVariants()[0]->getReturnType();
            }

            if ($enumCast && $type->isEnum()->yes()) {
                $values = [];

                foreach ($type->getEnumCases() as $case) {
                    $values[] = $case->getBackingValueType() ?? new ConstantStringType($case->getEnumCaseName());
                }

                return $values === [] ? new MixedType() : TypeCombinator::union(...$values);
            }

            return $type;
        });
    }
}
