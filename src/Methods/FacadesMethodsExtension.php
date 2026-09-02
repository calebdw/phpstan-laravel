<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\StaticMethodReflection;
use CalebDW\PhpstanLaravel\Support\FacadeHelper;
use CalebDW\PhpstanLaravel\Support\RecursionGuard;
use CalebDW\PhpstanLaravel\Support\ReflectionHelper;
use Illuminate\Support\Facades\Facade;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;

use function array_key_exists;
use function assert;
use function class_exists;
use function sprintf;
use function strrpos;
use function substr;

final class FacadesMethodsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private FacadeHelper $facadeHelper,
        private ReflectionHelper $reflectionHelper,
    ) {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! $classReflection->is(Facade::class)) {
            return false;
        }

        $key = $classReflection->getName() . '-' . $methodName;

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key] !== false;
        }

        $result = RecursionGuard::run($key, function () use ($classReflection, $methodName, $key) {
            if ($this->reflectionHelper->hasMethodTag($classReflection, $methodName)) {
                return false;
            }

            $facadeClass = $classReflection->getName();

            $concrete = $this->facadeHelper->getRoot($facadeClass);

            if ($concrete !== null) {
                $concreteClass = $concrete::class;

                if ($this->reflectionProvider->hasClass($concreteClass)) {
                    $concreteReflection = $this->reflectionProvider->getClass($concreteClass);

                    // Use hasNativeMethod() instead of hasMethod() to avoid
                    // re-entering registered MethodsClassReflectionExtensions
                    // (including this one), which would cause infinite recursion.
                    if ($concreteReflection->hasNativeMethod($methodName)) {
                        $this->cache[$key] = new StaticMethodReflection(
                            $concreteReflection->getNativeMethod($methodName),
                        );

                        return true;
                    }
                }
            }

            // The fake is only consulted for methods the real class does not
            // have, which is where its inspection API lives: pushed(), sent()
            // and dispatched() alongside the assertions.
            $fakeFacadeClass = $this->getFake($facadeClass);

            if ($this->reflectionProvider->hasClass($fakeFacadeClass)) {
                assert(class_exists($fakeFacadeClass));
                $fakeReflection = $this->reflectionProvider->getClass($fakeFacadeClass);

                if ($fakeReflection->hasNativeMethod($methodName)) {
                    $this->cache[$key] = new StaticMethodReflection(
                        $fakeReflection->getNativeMethod($methodName),
                    );

                    return true;
                }
            }

            return false;
        });

        if ($result === false) {
            $this->cache[$key] = false;
        }

        return $result ?? false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->cache[$classReflection->getName() . '-' . $methodName];
        assert($method !== false);

        return $method;
    }

    private function getFake(string $facade): string
    {
        $shortClassName = substr($facade, strrpos($facade, '\\') + 1);

        return sprintf('\\Illuminate\\Support\\Testing\\Fakes\\%sFake', $shortClassName);
    }
}
