<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\MacroMethodReflection;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use CalebDW\PhpstanLaravel\Support\FacadeHelper;
use Closure;
use Illuminate\Auth\RequestGuard;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ClosureTypeFactory;
use ReflectionFunction;

use function array_key_exists;
use function array_keys;
use function assert;
use function explode;
use function get_class;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function str_contains;

class MacroMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $methods = [];

    /** @var array<string, array<string, bool>> */
    private array $traitCache = [];

    /** @var array<string, array{class-string[], string|null}> */
    private array $macroSources = [];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private ClosureTypeFactory $closureTypeFactory,
        private ContainerHelper $containerHelper,
        private FacadeHelper $facadeHelper,
    ) {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        $cacheKey = $classReflection->getCacheKey() . '-' . $methodName;

        return ($this->methods[$cacheKey] ??= $this->findMethod($classReflection, $methodName)) !== false;
    }

    private function findMethod(ClassReflection $classReflection, string $methodName): MethodReflection|false
    {
        [$classNames, $macroTraitProperty] = $this->getMacroSources($classReflection);

        if ($classNames !== [] && $macroTraitProperty) {
            foreach ($classNames as $className) {
                $macroClassReflection = $this->reflectionProvider->getClass($className);

                if (! $macroClassReflection->getNativeReflection()->hasProperty($macroTraitProperty)) {
                    continue;
                }

                $refProperty = $macroClassReflection->getNativeReflection()->getProperty($macroTraitProperty);
                $macros      = $refProperty->getValue();

                if (! array_key_exists($methodName, $macros)) {
                    continue;
                }

                $macroDefinition = $macros[$methodName];

                if (is_string($macroDefinition)) {
                    if (str_contains($macroDefinition, '::')) {
                        $macroDefinition = explode('::', $macroDefinition, 2);
                        $macroClassName  = $macroDefinition[0];
                        if (! $this->reflectionProvider->hasClass($macroClassName) || ! $this->reflectionProvider->getClass($macroClassName)->hasNativeMethod($macroDefinition[1])) {
                            throw new ShouldNotHappenException('Class ' . $macroClassName . ' does not exist');
                        }

                        $methodReflection = $this->reflectionProvider->getClass($macroClassName)->getNativeMethod($macroDefinition[1]);
                    } elseif (is_callable($macroDefinition)) {
                        $methodReflection = new MacroMethodReflection(
                            $macroClassReflection,
                            $methodName,
                            $this->closureTypeFactory->fromClosureObject(Closure::fromCallable($macroDefinition)),
                        );
                    } else {
                        throw new ShouldNotHappenException('Function ' . $macroDefinition . ' does not exist');
                    }
                } elseif (is_array($macroDefinition)) {
                    if (is_string($macroDefinition[0])) {
                        $macroClassName = $macroDefinition[0];
                    } else {
                        $macroClassName = get_class($macroDefinition[0]);
                    }

                    if ($macroClassName === false || ! $this->reflectionProvider->hasClass($macroClassName) || ! $this->reflectionProvider->getClass($macroClassName)->hasNativeMethod($macroDefinition[1])) {
                        throw new ShouldNotHappenException('Class ' . $macroClassName . ' does not exist');
                    }

                    $methodReflection = $this->reflectionProvider->getClass($macroClassName)->getNativeMethod($macroDefinition[1]);
                } else {
                    $methodReflection = new MacroMethodReflection(
                        $macroClassReflection,
                        $methodName,
                        $this->closureTypeFactory->fromClosureObject($macroDefinition),
                        /** @phpstan-ignore phpstanApi.runtimeReflection */
                        (new ReflectionFunction($macroDefinition))->isStatic(),
                    );
                }

                return $methodReflection;
            }
        }

        return false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->methods[$classReflection->getCacheKey() . '-' . $methodName];
        assert($method !== false);

        return $method;
    }

    /** @return array{class-string[], string|null} */
    private function getMacroSources(ClassReflection $classReflection): array
    {
        $cacheKey = $classReflection->getCacheKey();

        if (array_key_exists($cacheKey, $this->macroSources)) {
            return $this->macroSources[$cacheKey];
        }

        /** @var class-string[] $classNames */
        $classNames         = [];
        $macroTraitProperty = null;

        if ($classReflection->isInterface() && Str::startsWith($classReflection->getName(), 'Illuminate\Contracts')) {
            /** @var object|null $concrete */
            $concrete = $this->containerHelper->resolve($classReflection->getName());

            if ($concrete !== null) {
                $className = $concrete::class;

                if ($className && $this->reflectionProvider->getClass($className)->hasTraitUse(Macroable::class)) {
                    $classNames         = [$className];
                    $macroTraitProperty = 'macros';
                }
            }
        } elseif (
            $this->hasIndirectTraitUse($classReflection, Macroable::class) ||
            $classReflection->is(Builder::class) ||
            $classReflection->is(QueryBuilder::class)
        ) {
            $classNames         = [$classReflection->getName()];
            $macroTraitProperty = 'macros';

            if ($classReflection->is(Builder::class)) {
                $classNames[] = Builder::class;
            }
        } elseif ($classReflection->is(Facade::class)) {
            $facadeClass = $classReflection->getName();

            if ($facadeClass === Auth::class) {
                $classNames         = [SessionGuard::class, RequestGuard::class];
                $macroTraitProperty = 'macros';
            } elseif ($facadeClass === Cache::class) {
                $classNames         = [CacheManager::class, CacheRepository::class];
                $macroTraitProperty = 'macros';
            } else {
                $concrete = $this->facadeHelper->getRoot($facadeClass);

                if ($concrete) {
                    $facadeClassName = $concrete::class;

                    if ($facadeClassName) {
                        $classNames         = [$facadeClassName];
                        $macroTraitProperty = 'macros';
                    }
                }
            }
        }

        return $this->macroSources[$cacheKey] = [$classNames, $macroTraitProperty];
    }

    private function hasIndirectTraitUse(ClassReflection $class, string $traitName): bool
    {
        $className = $class->getName();

        if (array_key_exists($className, $this->traitCache) && array_key_exists($traitName, $this->traitCache[$className])) {
            return $this->traitCache[$className][$traitName];
        }

        $this->traitCache[$className][$traitName] = in_array($traitName, array_keys($class->getTraits(true)), true);

        return $this->traitCache[$className][$traitName];
    }
}
