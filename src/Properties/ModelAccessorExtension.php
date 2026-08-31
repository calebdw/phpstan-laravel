<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties;

use CalebDW\PhpstanLaravel\Support\ModelPropertyHelper;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;

final class ModelAccessorExtension implements PropertiesClassReflectionExtension
{
    public function __construct(
        private ModelPropertyHelper $modelPropertyHelper,
    ) {
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        return $this->modelPropertyHelper->hasAccessor($classReflection, $propertyName);
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        return $this->modelPropertyHelper->getAccessor($classReflection, $propertyName);
    }
}
