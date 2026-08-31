<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties;

use CalebDW\PhpstanLaravel\Support\HigherOrderCollectionProxyHelper;
use Illuminate\Database\Eloquent\Collection;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type;

final class HigherOrderCollectionProxyPropertyExtension implements PropertiesClassReflectionExtension
{
    public function __construct(private HigherOrderCollectionProxyHelper $higherOrderCollectionProxyHelper)
    {
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        return $this->higherOrderCollectionProxyHelper->hasPropertyOrMethod($classReflection, $propertyName, 'property');
    }

    public function getProperty(
        ClassReflection $classReflection,
        string $propertyName,
    ): PropertyReflection {
        $activeTemplateTypeMap = $classReflection->getActiveTemplateTypeMap();

        /** @var Type\Constant\ConstantStringType $methodType */
        $methodType = $activeTemplateTypeMap->getType('T');

        /** @var Type\ObjectType $modelType */
        $modelType = $activeTemplateTypeMap->getType('TValue');

        /** @var Type\Type $collectionType */
        $collectionType = $activeTemplateTypeMap->getType('TCollection');

        $propertyType = $modelType->getInstanceProperty($propertyName, new OutOfClassScope())->getReadableType();

        if ($collectionType->getObjectClassNames() !== []) {
            $collectionClassName = $collectionType->getObjectClassNames()[0];
        } else {
            $collectionClassName = Collection::class;
        }

        $returnType = $this->higherOrderCollectionProxyHelper->determineReturnType(
            $methodType->getValue(),
            $modelType,
            $propertyType,
            $collectionClassName,
            $collectionType->getIterableKeyType(),
        );

        return new ModelProperty($classReflection, $returnType, $returnType, writeable: false);
    }
}
