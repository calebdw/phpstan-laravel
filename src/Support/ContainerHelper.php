<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Throwable;

final class ContainerHelper
{
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

    public function getConfigRepository(): ConfigRepository|null
    {
        $config = $this->resolve('config');

        return $config instanceof ConfigRepository ? $config : null;
    }
}
