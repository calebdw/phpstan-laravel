<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Internal\RecursionGuard;
use CalebDW\PhpstanLaravel\Reflection\StaticMethodReflection;
use CalebDW\PhpstanLaravel\Support\ManagerHelper;
use Illuminate\Support\Manager;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

/** @internal */
final class ManagersMethodsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection> */
    private array $cache = [];

    public function __construct(private ManagerHelper $managerHelper)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! $classReflection->is(Manager::class) || $classReflection->isAbstract()) {
            return false;
        }

        $key = $classReflection->getName() . '-' . $methodName;

        if (isset($this->cache[$key])) {
            return true;
        }

        $result = RecursionGuard::run($key, function () use ($classReflection, $methodName, $key) {
            foreach ($this->managerHelper->getDriverTypes($classReflection) as $type) {
                if (! $type->hasMethod($methodName)->yes()) {
                    continue;
                }

                $this->cache[$key] = new StaticMethodReflection(
                    $type->getMethod($methodName, new OutOfClassScope()),
                );

                return true;
            }

            return false;
        });

        return $result ?? false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return $this->cache[$classReflection->getName() . '-' . $methodName];
    }
}
