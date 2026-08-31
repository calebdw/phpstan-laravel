<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\StaticMethodReflection;
use CalebDW\PhpstanLaravel\Support\RecursionGuard;
use CalebDW\PhpstanLaravel\Support\ReflectionHelper;
use Illuminate\Support\Facades\Facade;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use Throwable;

use function assert;
use function class_exists;
use function sprintf;
use function strrpos;
use function substr;

final class FacadesMethodsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection> */
    private array $cache = [];

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! $classReflection->is(Facade::class)) {
            return false;
        }

        $key = $classReflection->getName() . '-' . $methodName;

        if (isset($this->cache[$key])) {
            return true;
        }

        $result = RecursionGuard::run($key, function () use ($classReflection, $methodName, $key) {
            if (ReflectionHelper::hasMethodTag($classReflection, $methodName)) {
                return false;
            }

            $facadeClass = $classReflection->getName();

            $concrete = null;

            try {
                $concrete = $facadeClass::getFacadeRoot();
            } catch (Throwable) {
            }

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

        return $result ?? false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return $this->cache[$classReflection->getName() . '-' . $methodName];
    }

    private function getFake(string $facade): string
    {
        $shortClassName = substr($facade, strrpos($facade, '\\') + 1);

        return sprintf('\\Illuminate\\Support\\Testing\\Fakes\\%sFake', $shortClassName);
    }
}
