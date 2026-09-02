<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use Illuminate\Support\HigherOrderTapProxy;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

use function assert;

// PHPStan does not resolve methods from a generic @mixin, so expose methods from the proxy's target explicitly.
final class HigherOrderTapProxyExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if ($classReflection->getName() !== HigherOrderTapProxy::class) {
            return false;
        }

        $cacheKey = $classReflection->getCacheKey() . '-' . $methodName;

        return ($this->cache[$cacheKey] ??= $this->findMethod($classReflection, $methodName)) !== false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->cache[$classReflection->getCacheKey() . '-' . $methodName];
        assert($method !== false);

        return $method;
    }

    private function findMethod(ClassReflection $classReflection, string $methodName): MethodReflection|false
    {
        $templateType = $classReflection->getActiveTemplateTypeMap()->getType('TTarget');

        if ($templateType === null || ! $templateType->hasMethod($methodName)->yes()) {
            return false;
        }

        return $templateType->getMethod($methodName, new OutOfClassScope());
    }
}
