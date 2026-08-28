<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use CalebDW\PhpstanLaravel\Concerns\HasContainer;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Throwable;

use function array_key_exists;
use function is_object;
use function is_string;
use function preg_match;

/** @internal */
final class ManagerHelper
{
    use HasContainer;

    /** @var array<string, string|null> */
    private array $defaultDrivers = [];

    /** @var array<string, Type|null> */
    private array $driverTypes = [];

    /** @var array<string, list<Type>> */
    private array $declaredDriverTypes = [];

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    /**
     * Returns the type of the given manager's driver, or null when it cannot
     * be determined.
     */
    public function getDriverType(ClassReflection $manager, string|null $driver = null): Type|null
    {
        if (! $this->isConcreteManager($manager)) {
            return null;
        }

        $driver ??= $this->getDefaultDriver($manager);

        if ($driver === null) {
            return null;
        }

        return $this->resolveDriverType($manager, $driver);
    }

    /**
     * Returns the types the given manager's driver may have.
     *
     * Unlike getDriverType(), this falls back to every driver the manager
     * declares when the requested driver cannot be determined - the default
     * driver usually comes from config, which is not always available.
     *
     * @return list<Type>
     */
    public function getDriverTypes(ClassReflection $manager, string|null $driver = null): array
    {
        if (! $this->isConcreteManager($manager)) {
            return [];
        }

        $driver ??= $this->getDefaultDriver($manager);

        if ($driver === null) {
            return $this->getDeclaredDriverTypes($manager);
        }

        $type = $this->resolveDriverType($manager, $driver);

        return $type === null ? [] : [$type];
    }

    private function isConcreteManager(ClassReflection $manager): bool
    {
        return $manager->is(Manager::class) && ! $manager->isAbstract();
    }

    /**
     * The declared return type of the creator method is preferred so that a
     * manager returning a contract only exposes the contract, falling back to
     * the driver resolved from the container for dynamically registered drivers.
     */
    private function resolveDriverType(ClassReflection $manager, string $driver): Type|null
    {
        $key = $manager->getName() . '::' . $driver;

        if (array_key_exists($key, $this->driverTypes)) {
            return $this->driverTypes[$key];
        }

        return $this->driverTypes[$key] = $this->getCreatorReturnType($manager, 'create' . Str::studly($driver) . 'Driver')
            ?? $this->getResolvedDriverType($manager, $driver);
    }

    /** @return list<Type> */
    private function getDeclaredDriverTypes(ClassReflection $manager): array
    {
        $key = $manager->getName();

        if (array_key_exists($key, $this->declaredDriverTypes)) {
            return $this->declaredDriverTypes[$key];
        }

        $types = [];

        foreach ($manager->getNativeReflection()->getMethods() as $method) {
            if (preg_match('/^create.+Driver$/', $method->getName()) !== 1) {
                continue;
            }

            $type = $this->getCreatorReturnType($manager, $method->getName());

            if ($type === null) {
                continue;
            }

            $types[] = $type;
        }

        return $this->declaredDriverTypes[$key] = $types;
    }

    private function getCreatorReturnType(ClassReflection $manager, string $creator): Type|null
    {
        if (! $manager->hasNativeMethod($creator)) {
            return null;
        }

        $type = ParametersAcceptorSelector::selectFromTypes(
            [],
            $manager->getNativeMethod($creator)->getVariants(),
            false,
        )->getReturnType();

        // Creators without a declared return type are of no use here.
        return $type->getObjectClassNames() === [] ? null : $type;
    }

    private function getResolvedDriverType(ClassReflection $manager, string $driver): Type|null
    {
        $concrete = $this->resolve($manager->getName());

        if (! $concrete instanceof Manager) {
            return null;
        }

        try {
            $instance = $concrete->driver($driver);
        } catch (Throwable) {
            return null;
        }

        if (! is_object($instance) || ! $this->reflectionProvider->hasClass($instance::class)) {
            return null;
        }

        return new ObjectType($instance::class);
    }

    private function getDefaultDriver(ClassReflection $manager): string|null
    {
        $key = $manager->getName();

        if (array_key_exists($key, $this->defaultDrivers)) {
            return $this->defaultDrivers[$key];
        }

        $this->defaultDrivers[$key] = null;

        $concrete = $this->resolve($key);

        if (! $concrete instanceof Manager) {
            return null;
        }

        try {
            $driver = $concrete->getDefaultDriver();
        } catch (Throwable) {
            return null;
        }

        if (! is_string($driver) || $driver === '') {
            return null;
        }

        return $this->defaultDrivers[$key] = $driver;
    }
}
