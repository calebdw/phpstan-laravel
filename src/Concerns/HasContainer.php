<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Concerns;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Throwable;

trait HasContainer
{
    protected ContainerContract|null $container = null;

    public function setContainer(ContainerContract $container): void
    {
        $this->container = $container;
    }

    /**
     * Returns the current broker.
     */
    public function getContainer(): ContainerContract
    {
        return $this->container ?? Container::getInstance();
    }

    /**
     * Resolve the given type from the container.
     */
    public function resolve(string $abstract): mixed
    {
        try {
            $concrete = $this->getContainer()->make($abstract);
        } catch (Throwable) {
            return null;
        }

        return $concrete;
    }

    private function getConfigRepository(): ConfigRepository|null
    {
        $config = $this->resolve('config');

        return $config instanceof ConfigRepository ? $config : null;
    }
}
