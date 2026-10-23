<?php

namespace OctaneResolvedInjection;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

class Consumer
{
    public function __construct(mixed $name, mixed $dependency = null)
    {
    }
}

function test(Application $app, bool $flag): void
{
    $app->singleton(Consumer::class, fn ($a) => new Consumer('name', $a));
    $app->singleton(Consumer::class, fn ($a) => new Consumer($a->make('request')));
    $app->singleton(Consumer::class, fn ($a) => new Consumer($a->get(id: 'config')));
    $app->singleton(Consumer::class, fn ($a) => new Consumer($a->makeWith(abstract: Request::class, parameters: [])));
    $app->singleton(Consumer::class, fn ($a) => new Consumer($a[Request::class]));
    $app->singleton(Consumer::class, fn ($a) => new Consumer(dependency: $a, name: 'name'));

    $key = $flag ? 'request' : '\Illuminate\Contracts\Config\Repository';
    $app->singleton(Consumer::class, fn ($a) => new Consumer($a[$key]));

    $app->singleton(Consumer::class, fn ($a) => new Consumer($a['config']->get('app.name')));
    $app->singleton(Consumer::class, fn ($a) => new Consumer($a->get('config')->get('app.name')));
    $app->singleton(Consumer::class, fn ($a) => new Consumer($a->make('cache')));
    $app->bind(Consumer::class, fn ($a) => new Consumer($a->make('request')));
}
