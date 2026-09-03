<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container as ContainerContract;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ErrorType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Throwable;

use function is_object;

final class ContainerHelper
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private readonly bool $strictContracts = false,
    ) {
    }

    public function getContainer(): ContainerContract
    {
        return Container::getInstance();
    }

    public function resolve(string $abstract): mixed
    {
        try {
            return $this->getContainer()->make($abstract);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param string|non-empty-list<string> $abstracts */
    public function getType(string|array $abstracts): Type
    {
        $types = [];

        foreach ((array) $abstracts as $abstract) {
            $types[] = $this->getTypeForName($abstract);
        }

        return TypeCombinator::union(...$types);
    }

    /**
     * Class names are taken at face value under strict contracts, and are also
     * the fallback when the container cannot build them here, so that code
     * depending on a contract is not typed as whatever concrete class happens
     * to be bound in this environment.
     */
    private function getTypeForName(string $abstract): Type
    {
        $isClassName = $this->reflectionProvider->hasClass($abstract);

        if ($this->strictContracts && $isClassName) {
            return new ObjectType($abstract);
        }

        $resolved = $this->resolve($abstract);

        if (is_object($resolved)) {
            return new ObjectType($resolved::class);
        }

        if ($resolved === null && $isClassName) {
            return new ObjectType($abstract);
        }

        return new ErrorType();
    }

    public function getConfigRepository(): ConfigRepository|null
    {
        $config = $this->resolve('config');

        return $config instanceof ConfigRepository ? $config : null;
    }
}
