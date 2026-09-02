<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties;

use CalebDW\PhpstanLaravel\Reflection\ModelPropertyReflection;
use CalebDW\PhpstanLaravel\Support\HigherOrderCollectionProxyHelper;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;

use function assert;

final class HigherOrderCollectionProxyPropertyExtension implements PropertiesClassReflectionExtension
{
    public function __construct(private HigherOrderCollectionProxyHelper $higherOrderCollectionProxyHelper)
    {
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        return $this->higherOrderCollectionProxyHelper->hasPropertyOrMethod($classReflection, $propertyName, 'property');
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $templates = $this->higherOrderCollectionProxyHelper->getProxyTemplates($classReflection);
        assert($templates !== null);

        $propertyType = $templates['value']->getInstanceProperty($propertyName, new OutOfClassScope())->getReadableType();

        $returnType = $this->higherOrderCollectionProxyHelper->determineReturnType(
            $templates['methods'],
            $templates['value'],
            $propertyType,
            $templates['collection']->getObjectClassNames(),
            $templates['collection']->getIterableKeyType(),
        );

        return new ModelPropertyReflection($classReflection, $returnType, $returnType, writeable: false);
    }
}
