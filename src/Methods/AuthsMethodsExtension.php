<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\StaticMethodReflection;
use CalebDW\PhpstanLaravel\Support\AuthModelHelper;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;

use function assert;
use function in_array;

final class AuthsMethodsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    /** @var string[] */
    private array $authContracts = [
        Authenticatable::class,
        CanResetPassword::class,
        Authorizable::class,
    ];

    public function __construct(private ReflectionProvider $reflectionProvider, private AuthModelHelper $authModelHelper)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        $className = $classReflection->getName();

        if (
            ! in_array($className, $this->authContracts, true)
            && $className !== Factory::class
            && $className !== AuthManager::class
        ) {
            return false;
        }

        $cacheKey = $className . '-' . $methodName;

        return ($this->cache[$cacheKey] ??= $this->findMethod($className, $methodName)) !== false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->cache[$classReflection->getName() . '-' . $methodName];
        assert($method !== false);

        return $method;
    }

    private function findMethod(string $className, string $methodName): MethodReflection|false
    {
        if ($className === Factory::class || $className === AuthManager::class) {
            return $this->findMethodOnClass(Guard::class, $methodName);
        }

        foreach ($this->authModelHelper->getModels() as $authModel) {
            $method = $this->findMethodOnClass($authModel, $methodName);

            if ($method !== false) {
                return $method;
            }
        }

        return false;
    }

    private function findMethodOnClass(string $class, string $methodName): MethodReflection|false
    {
        if (! $this->reflectionProvider->hasClass($class)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($class);

        if (! $classReflection->hasMethod($methodName)) {
            return false;
        }

        return new StaticMethodReflection($classReflection->getMethod($methodName, new OutOfClassScope()));
    }
}
