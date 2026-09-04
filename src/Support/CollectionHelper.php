<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Iterator;
use IteratorAggregate;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\UnionType;
use Traversable;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function in_array;

final class CollectionHelper
{
    /** @var array<string, Type|null> */
    private array $originalCollectionTypes = [];

    /** @var array<string, Type|null> */
    private array $collectionTypes = [];

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function generic(string $className, Type $keyType, Type $valueType): Type
    {
        if (! $this->reflectionProvider->hasClass($className)) {
            return new GenericObjectType(Collection::class, [$keyType, $valueType]);
        }

        $reflection = $this->reflectionProvider->getClass($className);

        if (! $reflection->isGeneric()) {
            return new ObjectType($className);
        }

        $typeMap = $reflection->getActiveTemplateTypeMap();

        if ($typeMap->count() === 2) {
            return new GenericObjectType($className, [$keyType, $valueType]);
        }

        if ($typeMap->count() === 1 && $typeMap->hasType('TModel')) {
            return new GenericObjectType($className, [$valueType]);
        }

        return new GenericObjectType($className, [$keyType, $valueType]);
    }

    public function determineGenericCollectionTypeFromType(Type $type): Type|null
    {
        $classReflections = $type->getObjectClassReflections();

        if ($classReflections !== []) {
            if ((new ObjectType(Enumerable::class))->isSuperTypeOf($type)->yes()) {
                $types = array_filter(array_map(
                    fn ($reflection) => $this->getTypeFromEloquentCollection($reflection),
                    $classReflections,
                ));

                return $types === [] ? null : TypeCombinator::union(...$types);
            }

            if (
                (new ObjectType(Traversable::class))->isSuperTypeOf($type)->yes()
                || (new ObjectType(IteratorAggregate::class))->isSuperTypeOf($type)->yes()
                || (new ObjectType(Iterator::class))->isSuperTypeOf($type)->yes()
            ) {
                return TypeCombinator::union(...array_map(
                    fn ($reflection) => $this->getTypeFromIterator($reflection),
                    $classReflections,
                ));
            }
        }

        if (! $type->isArray()->yes()) {
            return new GenericObjectType(Collection::class, [$type->toArray()->getIterableKeyType(), $type->toArray()->getIterableValueType()]);
        }

        if ($type->isIterableAtLeastOnce()->no()) {
            return new GenericObjectType(Collection::class, [new BenevolentUnionType([new IntegerType(), new StringType()]), new MixedType()]);
        }

        return null;
    }

    public function determineOriginalCollectionType(string $modelClassName): Type|null
    {
        if (array_key_exists($modelClassName, $this->originalCollectionTypes)) {
            return $this->originalCollectionTypes[$modelClassName];
        }

        return $this->originalCollectionTypes[$modelClassName] = $this->resolveOriginalCollectionType($modelClassName);
    }

    private function resolveOriginalCollectionType(string $modelClassName): Type|null
    {
        if (! $this->reflectionProvider->hasClass($modelClassName)) {
            return null;
        }

        $modelReflection = $this->reflectionProvider->getClass($modelClassName);

        if (! $modelReflection->is(Model::class)) {
            return null;
        }

        $attrs = $modelReflection->getNativeReflection()->getAttributes(CollectedBy::class);

        if ($attrs !== []) {
            $expr =  $attrs[0]->getArgumentsExpressions()[0];

            if ($expr instanceof ClassConstFetch && $expr->class instanceof Name) {
                return new ObjectType($expr->class->toString());
            }
        }

        return $modelReflection->getNativeMethod('newCollection')
            ->getVariants()[0]
            ->getReturnType();
    }

    public function determineCollectionType(string $modelClassName, Type|null $modelType = null): Type|null
    {
        if ($modelType === null) {
            if (array_key_exists($modelClassName, $this->collectionTypes)) {
                return $this->collectionTypes[$modelClassName];
            }

            return $this->collectionTypes[$modelClassName] = $this->resolveCollectionType($modelClassName, new ObjectType($modelClassName));
        }

        return $this->resolveCollectionType($modelClassName, $modelType);
    }

    private function resolveCollectionType(string $modelClassName, Type $modelType): Type|null
    {
        $collectionType = $this->determineOriginalCollectionType($modelClassName);

        if ($collectionType === null) {
            return null;
        }

        return TypeTraverser::map($collectionType, function (Type $type, callable $traverse) use ($modelType): Type {
            if ($type instanceof UnionType || $type instanceof IntersectionType) {
                return $traverse($type);
            }

            $classReflections = $type->getObjectClassReflections();

            if (count($classReflections) !== 1) {
                return $type;
            }

            $classReflection = $classReflections[0];

            if (! $classReflection->is(EloquentCollection::class) || ! $classReflection->isGeneric()) {
                return $type;
            }

            $keyType = new IntegerType();
            $typeMap = $classReflection->getActiveTemplateTypeMap();

            if ($typeMap->count() === 1 && ! $typeMap->hasType('TModel')) {
                return new GenericObjectType($classReflection->getName(), [$keyType]);
            }

            return $this->generic($classReflection->getName(), $keyType, $modelType);
        });
    }

    public function replaceCollectionsInType(Type $type): Type
    {
        if (! in_array(EloquentCollection::class, $type->getReferencedClasses(), true)) {
            return $type;
        }

        return TypeTraverser::map($type, function ($type, $traverse): Type {
            if ($type instanceof UnionType || $type instanceof IntersectionType) {
                return $traverse($type);
            }

            if (! (new ObjectType(EloquentCollection::class))->isSuperTypeOf($type)->yes()) {
                return $traverse($type);
            }

            $templateType = $type->getTemplateType(EloquentCollection::class, 'TModel');
            $models       = $templateType->getObjectClassNames();

            return match (count($models)) {
                0 => $type,
                1 => $this->determineCollectionType($models[0], $templateType) ?? $type,
                default => TypeCombinator::union(...array_filter(array_map(fn ($m) => $this->determineCollectionType($m), $models))),
            };
        });
    }

    private function getTypeFromEloquentCollection(ClassReflection $classReflection): GenericObjectType|null
    {
        $keyType = new BenevolentUnionType([new IntegerType(), new StringType()]);

        $innerValueType = $classReflection->getActiveTemplateTypeMap()->getType('TModel');

        if ($classReflection->is(EloquentCollection::class)) {
            $keyType = new IntegerType();
        }

        if ($innerValueType !== null) {
            return new GenericObjectType(Collection::class, [$keyType, $innerValueType]);
        }

        return null;
    }

    private function getTypeFromIterator(ClassReflection $classReflection): GenericObjectType
    {
        $keyType = new BenevolentUnionType([new IntegerType(), new StringType()]);

        $templateTypes = array_values($classReflection->getActiveTemplateTypeMap()->getTypes());

        if (count($templateTypes) === 1) {
            return new GenericObjectType(Collection::class, [$keyType, $templateTypes[0]]);
        }

        return new GenericObjectType(Collection::class, $templateTypes);
    }
}
