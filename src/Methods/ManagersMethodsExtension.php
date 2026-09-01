<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\StaticMethodReflection;
use CalebDW\PhpstanLaravel\Support\ManagerHelper;
use CalebDW\PhpstanLaravel\Support\RecursionGuard;
use Illuminate\Support\Manager;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

use function array_key_exists;
use function assert;

final class ManagersMethodsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    public function __construct(private ManagerHelper $managerHelper)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! $classReflection->is(Manager::class) || $classReflection->isAbstract()) {
            return false;
        }

        $cacheKey = $classReflection->getName() . '-' . $methodName;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey] !== false;
        }

        $method = RecursionGuard::run($cacheKey, fn () => $this->findMethod($classReflection, $methodName));

        // A recursive lookup has no answer yet, so it must not be remembered.
        if ($method === null) {
            return false;
        }

        return ($this->cache[$cacheKey] = $method) !== false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->cache[$classReflection->getName() . '-' . $methodName];
        assert($method !== false);

        return $method;
    }

    private function findMethod(ClassReflection $classReflection, string $methodName): MethodReflection|false
    {
        foreach ($this->managerHelper->getDriverTypes($classReflection) as $type) {
            if (! $type->hasMethod($methodName)->yes()) {
                continue;
            }

            return new StaticMethodReflection($type->getMethod($methodName, new OutOfClassScope()));
        }

        return false;
    }
}
