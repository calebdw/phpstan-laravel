<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\StaticMethodReflection;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use Illuminate\Support\Str;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;

use function assert;

final class ContractsMethodsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    public function __construct(private ReflectionProvider $reflectionProvider, private ContainerHelper $containerHelper)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! $classReflection->isInterface() || ! Str::startsWith($classReflection->getName(), 'Illuminate\Contracts')) {
            return false;
        }

        $cacheKey = $classReflection->getName() . '-' . $methodName;

        return ($this->cache[$cacheKey] ??= $this->findMethod($classReflection, $methodName)) !== false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->cache[$classReflection->getName() . '-' . $methodName];
        assert($method !== false);

        return $method;
    }

    private function findMethod(ClassReflection $classReflection, string $methodName): MethodReflection|false
    {
        $concrete = $this->containerHelper->resolve($classReflection->getName());

        if ($concrete === null) {
            return false;
        }

        $concreteClass = $concrete::class;

        if (! $this->reflectionProvider->hasClass($concreteClass)) {
            return false;
        }

        $concreteReflection = $this->reflectionProvider->getClass($concreteClass);

        if (! $concreteReflection->hasMethod($methodName)) {
            return false;
        }

        return new StaticMethodReflection(
            $concreteReflection->getMethod($methodName, new OutOfClassScope()),
        );
    }
}
