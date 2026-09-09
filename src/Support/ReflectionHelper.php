<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute as NativeAttribute;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\Mixin\MixinMethodsClassReflectionExtension;
use PHPStan\Reflection\Mixin\MixinPropertiesClassReflectionExtension;

use function array_filter;
use function array_key_exists;
use function array_values;
use function collect;
use function is_array;
use function is_string;

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

    /**
     * Whether the class, a parent, or an immediately used trait declares the
     * attribute. Matches Laravel's ReadsClassAttributes walk; nested traits
     * of traits are not inspected.
     */
    public function hasAttribute(ClassReflection $class, string $attribute, bool $inherited = true): bool
    {
        return $this->findAttribute($class, $attribute, $inherited) !== null;
    }

    public function hasMethodAttribute(ExtendedMethodReflection $method, string $attribute): bool
    {
        foreach ($method->getAttributes() as $attr) {
            if ($attr->getName() === $attribute) {
                return true;
            }
        }

        return false;
    }

    /**
     * First constructor argument when it is a class constant fetch.
     * Used by CollectedBy, UseFactory, and UseEloquentBuilder.
     */
    public function attributeClassName(ClassReflection $class, string $attribute, bool $inherited = true): string|null
    {
        $attr = $this->findAttribute($class, $attribute, $inherited);

        if ($attr === null) {
            return null;
        }

        $expr = $attr->getArgumentsExpressions()[0] ?? null;

        if (! $expr instanceof ClassConstFetch || ! $expr->class instanceof Name) {
            return null;
        }

        return $expr->class->toString();
    }

    /**
     * Constructor arguments that are strings, including a single list argument.
     * Used by #[Appends], #[Hidden], and #[Visible].
     *
     * @return list<string>
     */
    public function attributeStringArguments(ClassReflection $class, string $attribute, bool $inherited = true): array
    {
        $attr = $this->findAttribute($class, $attribute, $inherited);

        if ($attr === null) {
            return [];
        }

        $arguments = array_values($attr->getArguments());
        $columns   = is_array($arguments[0] ?? null) ? $arguments[0] : $arguments;

        return array_values(array_filter($columns, is_string(...)));
    }

    private function findAttribute(ClassReflection $class, string $attribute, bool $inherited): NativeAttribute|null
    {
        $reflections = $inherited ? [$class, ...$class->getParents()] : [$class];

        foreach ($reflections as $reflection) {
            $attr = $this->declaredAttribute($reflection, $attribute);

            if ($attr !== null) {
                return $attr;
            }

            if (! $inherited) {
                continue;
            }

            foreach ($reflection->getTraits() as $trait) {
                $attr = $this->declaredAttribute($trait, $attribute);

                if ($attr !== null) {
                    return $attr;
                }
            }
        }

        return null;
    }

    private function declaredAttribute(ClassReflection $class, string $attribute): NativeAttribute|null
    {
        foreach ($class->getNativeReflection()->getAttributes() as $attr) {
            if ($attr instanceof NativeAttribute && $attr->getName() === $attribute) {
                return $attr;
            }
        }

        return null;
    }
}
