<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use CalebDW\PhpstanLaravel\Support\ReflectionHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\UnionType;

use function assert;
use function str_ends_with;

final class ModelRelationsExtension implements PropertiesClassReflectionExtension
{
    /** @var array<string, ModelProperty|false> */
    private array $properties = [];

    private ObjectType $relationObjectType;

    public function __construct(
        private CollectionHelper $collectionHelper,
        private ReflectionHelper $reflectionHelper,
    ) {
        $this->relationObjectType = new ObjectType(Relation::class);
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        if (! $classReflection->is(Model::class)) {
            return false;
        }

        $cacheKey = $classReflection->getCacheKey() . '-' . $propertyName;

        return ($this->properties[$cacheKey] ??= $this->resolveProperty($classReflection, $propertyName)) !== false;
    }

    private function resolveProperty(ClassReflection $classReflection, string $propertyName): ModelProperty|false
    {
        if ($this->reflectionHelper->hasPropertyTag($classReflection, $propertyName)) {
            return false;
        }

        if (str_ends_with($propertyName, '_count')) {
            $relationName = Str::before($propertyName, '_count');

            $methodNames = [Str::camel($relationName), $relationName];
        } else {
            $methodNames = [$propertyName];
        }

        foreach ($methodNames as $methodName) {
            if (! $classReflection->hasNativeMethod($methodName)) {
                continue;
            }

            $returnType = $classReflection->getNativeMethod($methodName)->getVariants()[0]->getReturnType();

            if ($this->relationObjectType->isSuperTypeOf($returnType)->yes()) {
                if (str_ends_with($propertyName, '_count')) {
                    return new ModelProperty($classReflection, IntegerRangeType::createAllGreaterThanOrEqualTo(0), new NeverType(), false);
                }

                $relationType = TypeTraverser::map($returnType, function (Type $type, callable $traverse): Type {
                    if ($type instanceof UnionType || $type instanceof IntersectionType) {
                        return $traverse($type);
                    }

                    if (! $this->relationObjectType->isSuperTypeOf($type)->yes()) {
                        return $type;
                    }

                    return $type->getTemplateType(Relation::class, 'TResult');
                });

                $relationType = $this->collectionHelper->replaceCollectionsInType($relationType);

                return new ModelProperty($classReflection, $relationType, new NeverType(), false);
            }
        }

        return false;
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $property = $this->properties[$classReflection->getCacheKey() . '-' . $propertyName];
        assert($property !== false);

        return $property;
    }
}
