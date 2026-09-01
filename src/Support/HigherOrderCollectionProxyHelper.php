<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\HigherOrderCollectionProxy;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_map;

class HigherOrderCollectionProxyHelper
{
    /** @var array<string, bool> */
    private array $members = [];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private ColumnHelper $columnHelper,
    ) {
    }

    /** @phpstan-param 'method'|'property' $propertyOrMethod */
    public function hasPropertyOrMethod(ClassReflection $classReflection, string $name, string $propertyOrMethod): bool
    {
        if ($classReflection->getName() !== HigherOrderCollectionProxy::class) {
            return false;
        }

        $cacheKey = $classReflection->getCacheKey() . '-' . $propertyOrMethod . '-' . $name;

        return $this->members[$cacheKey] ??= $this->resolvePropertyOrMethod($classReflection, $name, $propertyOrMethod);
    }

    /** @return array{methods: non-empty-list<string>, value: Type, collection: Type}|null */
    public function getProxyTemplates(ClassReflection $classReflection): array|null
    {
        $templateTypeMap = $classReflection->getActiveTemplateTypeMap();

        if ($templateTypeMap->count() !== 3) {
            return null;
        }

        $methodType     = $templateTypeMap->getType('T');
        $valueType      = $templateTypeMap->getType('TValue');
        $collectionType = $templateTypeMap->getType('TCollection');

        if ($methodType === null || $valueType === null || $collectionType === null) {
            return null;
        }

        $methods = array_map(static fn ($m) => $m->getValue(), $methodType->getConstantStrings());

        if ($methods === []) {
            return null;
        }

        return [
            'methods' => $methods,
            'value' => $valueType,
            'collection' => $collectionType,
        ];
    }

    /** @phpstan-param 'method'|'property' $propertyOrMethod */
    private function resolvePropertyOrMethod(ClassReflection $classReflection, string $name, string $propertyOrMethod): bool
    {
        $templates = $this->getProxyTemplates($classReflection);

        if ($templates === null || ! $templates['value']->canCallMethods()->yes()) {
            return false;
        }

        if ($propertyOrMethod === 'method') {
            return $templates['value']->hasMethod($name)->yes();
        }

        return $templates['value']->hasInstanceProperty($name)->yes();
    }

    /**
     * @param  non-empty-list<string> $methods
     * @param  list<string>           $collectionClasses
     */
    public function determineReturnType(array $methods, Type $valueType, Type $methodOrPropertyReturnType, array $collectionClasses, Type $collectionKeyType): Type
    {
        if ($collectionClasses === []) {
            $collectionClasses = [Collection::class];
        }

        $types = [];

        foreach ($methods as $name) {
            foreach ($collectionClasses as $collectionClass) {
                $types[] = $this->determineSingleReturnType(
                    $name,
                    $valueType,
                    $methodOrPropertyReturnType,
                    $collectionClass,
                    $collectionKeyType,
                );
            }
        }

        return TypeCombinator::union(...$types);
    }

    private function determineSingleReturnType(string $name, Type $valueType, Type $methodOrPropertyReturnType, string $collectionType, Type $collectionKeyType): Type
    {
        $integerType = new IntegerType();

        switch ($name) {
            case 'average':
            case 'avg':
                $returnType = new FloatType();
                break;
            case 'contains':
            case 'every':
            case 'some':
                $returnType = new BooleanType();
                break;
            case 'each':
            case 'filter':
            case 'reject':
            case 'skipUntil':
            case 'skipWhile':
            case 'sortBy':
            case 'sortByDesc':
            case 'takeUntil':
            case 'takeWhile':
            case 'unique':
                $returnType = $this->getCollectionType($collectionType, $integerType, $valueType);
                break;
            case 'keyBy':
                $returnType = $this->getCollectionType(
                    $collectionType,
                    $this->columnHelper->normalizeKey($methodOrPropertyReturnType),
                    $valueType,
                );
                break;
            case 'first':
                $returnType = TypeCombinator::addNull($valueType);
                break;
            case 'flatMap':
                $returnType = $this->getCollectionType(SupportCollection::class, $integerType, new MixedType());
                break;
            case 'groupBy':
                $returnType = $this->getCollectionType(
                    $collectionType,
                    $this->columnHelper->normalizeGroupKey($methodOrPropertyReturnType),
                    $this->getCollectionType($collectionType, $integerType, $valueType),
                );
                break;
            case 'partition':
                // Always exactly two groups, keyed 0 and 1.
                $returnType = $this->getCollectionType($collectionType, $integerType, $this->getCollectionType($collectionType, $integerType, $valueType));
                break;
            case 'map':
                $returnType = $this->getCollectionType(
                    SupportCollection::class,
                    $collectionKeyType instanceof MixedType
                        ? new BenevolentUnionType([new IntegerType(), new StringType()])
                        : $collectionKeyType,
                    $methodOrPropertyReturnType,
                );
                break;
            case 'max':
            case 'min':
                $returnType = $methodOrPropertyReturnType;
                break;
            case 'sum':
                if ($methodOrPropertyReturnType->accepts(new IntegerType(), true)->yes()) {
                    $returnType = new IntegerType();
                } else {
                    $returnType = new ErrorType();
                }

                break;
            default:
                $returnType = new ErrorType();
                break;
        }

        return $returnType;
    }

    private function getCollectionType(string $collectionClassName, Type $keyType, Type $valueType): Type
    {
        $collectionReflection = $this->reflectionProvider->getClass($collectionClassName);

        if ($collectionReflection->isGeneric()) {
            $typeMap = $collectionReflection->getActiveTemplateTypeMap();

            // Specifies key and value
            if ($typeMap->count() === 2) {
                return new GenericObjectType($collectionClassName, [$keyType, $valueType]);
            }

            // Specifies only value
            if (($typeMap->count() === 1) && $typeMap->hasType('TModel')) {
                return new GenericObjectType($collectionClassName, [$valueType]);
            }
        }

        // Not generic. So return the type as is
        return new ObjectType($collectionClassName);
    }
}
