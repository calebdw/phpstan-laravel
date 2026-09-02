<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\Mixin\MixinMethodsClassReflectionExtension;
use PHPStan\Reflection\Mixin\MixinPropertiesClassReflectionExtension;

use function array_key_exists;
use function collect;

final class ReflectionHelper
{
    /** @var array<string, bool> */
    private array $propertyTags = [];

    /** @var array<string, bool> */
    private array $methodTags = [];

    /**
     * Does the given class or any of its ancestors have an `@property*` annotation with the given name?
     */
    public function hasPropertyTag(ClassReflection $classReflection, string $propertyName): bool
    {
        $cacheKey = $classReflection->getCacheKey() . '-' . $propertyName;

        if (array_key_exists($cacheKey, $this->propertyTags)) {
            return $this->propertyTags[$cacheKey];
        }

        if (
            array_key_exists($propertyName, $classReflection->getPropertyTags())
            || collect($classReflection->getAncestors())
                ->contains(static fn ($a) => array_key_exists($propertyName, $a->getPropertyTags()))
        ) {
            return $this->propertyTags[$cacheKey] = true;
        }

        /** @phpstan-ignore phpstanApi.method, phpstanApi.constructor (no public API answers whether a mixin supplies the member) */
        return $this->propertyTags[$cacheKey] = (new MixinPropertiesClassReflectionExtension([$classReflection->getName()]))
            ->hasProperty($classReflection, $propertyName);
    }

    /**
     * Does the given class or any of its ancestors have an `@method*` annotation with the given name?
     */
    public function hasMethodTag(ClassReflection $classReflection, string $methodName): bool
    {
        $cacheKey = $classReflection->getCacheKey() . '-' . $methodName;

        if (array_key_exists($cacheKey, $this->methodTags)) {
            return $this->methodTags[$cacheKey];
        }

        if (
            array_key_exists($methodName, $classReflection->getMethodTags())
            || collect($classReflection->getAncestors())
                ->contains(static fn ($a) => array_key_exists($methodName, $a->getMethodTags()))
        ) {
            return $this->methodTags[$cacheKey] = true;
        }

        /** @phpstan-ignore phpstanApi.method, phpstanApi.constructor (no public API answers whether a mixin supplies the member) */
        return $this->methodTags[$cacheKey] = (new MixinMethodsClassReflectionExtension([$classReflection->getName()]))
            ->hasMethod($classReflection, $methodName);
    }
}
