<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties;

use CalebDW\PhpstanLaravel\Reflection\ModelPropertyReflection;
use CalebDW\PhpstanLaravel\Support\ValidationHelper;
use Illuminate\Foundation\Http\FormRequest;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;

use function assert;

final class FormRequestPropertyExtension implements PropertiesClassReflectionExtension
{
    /** @var array<string, ModelPropertyReflection|false> */
    private array $properties = [];

    public function __construct(private ValidationHelper $validationHelper)
    {
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        if (! $classReflection->is(FormRequest::class)) {
            return false;
        }

        $cacheKey = $classReflection->getCacheKey() . '-' . $propertyName;

        return ($this->properties[$cacheKey] ??= $this->resolveProperty($classReflection, $propertyName)) !== false;
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $property = $this->properties[$classReflection->getCacheKey() . '-' . $propertyName];
        assert($property !== false);

        return $property;
    }

    private function resolveProperty(ClassReflection $classReflection, string $propertyName): ModelPropertyReflection|false
    {
        $type = $this->validationHelper->propertyType($classReflection, $propertyName);

        if ($type === null) {
            return false;
        }

        return new ModelPropertyReflection($classReflection, $type, $type);
    }
}
