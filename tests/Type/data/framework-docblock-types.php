<?php

namespace FrameworkDocblockTypes;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

use function PHPStan\Testing\assertType;

function dispatching(Dispatcher $events, DispatcherContract $contract, bool $halt): void
{
    // Laravel documents all of these as array|null. Halting hands back one
    // listener's answer instead of the list, and nothing at all when the
    // dispatch was deferred.
    assertType('list<mixed>|null', $events->dispatch('x'));
    assertType('list<mixed>|null', $events->dispatch('x', [], false));
    assertType('mixed', $events->dispatch('x', [], true));
    assertType('mixed', $events->dispatch('x', [], $halt));
    assertType('mixed', $events->until('x'));

    assertType('list<mixed>|null', $contract->dispatch('x'));
    assertType('mixed', $contract->dispatch('x', [], true));
    assertType('mixed', $contract->until('x'));

    // the facade reaches them through the contract rather than through the
    // @method annotations Laravel writes on it
    assertType('list<mixed>|null', Event::dispatch('x'));
    assertType('mixed', Event::dispatch('x', [], true));
    assertType('mixed', Event::until('x'));
}

function other(Kernel $kernel, Route $route): void
{
    assertType('array<string, Symfony\Component\Console\Command\Command>', $kernel->all());
    assertType('array<string>', $route->methods());

    // Laravel types the builder itself but not the facade in front of it
    assertType('list<string>', Schema::getColumnListing('users'));
}
