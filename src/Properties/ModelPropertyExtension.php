<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties;

use CalebDW\PhpstanLaravel\Support\ModelPropertyHelper;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;

final class ModelPropertyExtension implements PropertiesClassReflectionExtension
{
    public function __construct(
        private ModelPropertyHelper $modelPropertyHelper,
    ) {
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        if ($this->modelPropertyHelper->hasAccessor($classReflection, $propertyName)) {
            return false;
        }

        return $this->modelPropertyHelper->hasDatabaseProperty($classReflection, $propertyName);
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        return $this->modelPropertyHelper->getDatabaseProperty($classReflection, $propertyName);
    }
}
