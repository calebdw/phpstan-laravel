<?php

namespace ContainerMake;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Container\Container;

use function PHPStan\Testing\assertType;

/** @param class-string<Model> $model */
function test(
    Container $container,
    ContainerContract $container2,
    string $model,
    mixed $mixed,
): void {
    assertType('Illuminate\Config\Repository', $container->make(Repository::class));
    assertType('Illuminate\Config\Repository', $container->makeWith(Repository::class));
    assertType('Illuminate\Config\Repository', $container->resolve(Repository::class));

    assertType('Illuminate\Config\Repository', $container2->make(Repository::class));
    assertType('Illuminate\Config\Repository', $container2->makeWith(Repository::class));
    assertType('Illuminate\Config\Repository', $container2->resolve(Repository::class));

    assertType('Illuminate\Database\Eloquent\Model', $container->make($model));
    assertType('Illuminate\Database\Eloquent\Model', $container->makeWith($model));
    assertType('Illuminate\Database\Eloquent\Model', $container->resolve($model));
    assertType('Illuminate\Database\Eloquent\Model', $container2->make($model));
    assertType('Illuminate\Database\Eloquent\Model', $container2->makeWith($model));
    assertType('Illuminate\Database\Eloquent\Model', $container2->resolve($model));

    assertType('mixed', $container->make($mixed));
    assertType('mixed', $container->makeWith($mixed));
    assertType('mixed', $container->resolve($mixed));
}
