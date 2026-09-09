<?php

declare(strict_types=1);

namespace CacheRemember;

use App\User;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Support\Facades\Cache;

use function PHPStan\Testing\assertType;

function testFacade(int $id, CacheContract $repository): void
{
    assertType('App\User', Cache::remember(
        "user:$id",
        3600,
        fn () => User::findOrFail($id),
    ));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', Cache::remember(
        'users',
        3600,
        fn () => User::query()->get(),
    ));

    assertType('App\User', Cache::rememberForever(
        "user:$id",
        fn () => User::findOrFail($id),
    ));

    assertType('App\User|null', Cache::remember(
        "user:$id",
        3600,
        fn () => User::find($id),
    ));

    assertType("'missing'|App\User", Cache::remember(
        "user:$id",
        3600,
        fn () => User::find($id) ?? 'missing',
    ));

    assertType('123', Cache::remember(
        'literal',
        60,
        static fn (): int => 123,
    ));

    assertType('App\User', Cache::flexible(
        "user:$id",
        [60, 3600],
        fn () => User::findOrFail($id),
    ));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', Cache::flexible(
        'users',
        [60, 3600],
        fn () => User::all(),
    ));

    assertType('App\User', Cache::store()->remember(
        "user:$id",
        3600,
        fn () => User::findOrFail($id),
    ));

    assertType('App\User', Cache::driver()->rememberForever(
        "user:$id",
        fn () => User::findOrFail($id),
    ));

    assertType('App\User', $repository->remember(
        "user:$id",
        3600,
        fn () => User::findOrFail($id),
    ));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $repository->rememberForever(
        'users',
        fn () => User::query()->get(),
    ));

    assertType('App\User', cache()->remember(
        "user:$id",
        3600,
        fn () => User::findOrFail($id),
    ));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', cache()->rememberForever(
        'users',
        fn () => User::all(),
    ));

    assertType('App\User', cache()->flexible(
        "user:$id",
        [60, 3600],
        fn () => User::findOrFail($id),
    ));

    $callback = fn () => User::findOrFail($id);
    assertType('App\User', Cache::remember('key', 60, $callback));

    assertType('App\User', Cache::sear(
        "user:$id",
        fn () => User::findOrFail($id),
    ));
}

/** @param Closure(): (User|null) $callback */
function testClosureParam(Closure $callback): void
{
    assertType('App\User|null', Cache::remember('key', 60, $callback));
}
