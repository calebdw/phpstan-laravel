<?php

declare(strict_types=1);

namespace CollectionReduceSpread;

use App\User;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, int>  $ints
 * @param  Collection<int, User>  $users
 */
function test(Collection $ints, Collection $users): void
{
    assertType('array{int, int}', $ints->reduceSpread(fn ($sum, $prod, $n) => [$sum + $n, $prod * $n], 0, 1));

    $ints->reduceSpread(function ($sum, $prod, $n, $key) {
        assertType('int', $sum);
        assertType('int', $prod);
        assertType('int', $n);
        assertType('int', $key);

        return [$sum + $n, $prod * $n];
    }, 0, 1);

    assertType('array{int}', $users->reduceSpread(fn ($sum, $u) => [$sum + $u->id], 0));

    $users->reduceSpread(function ($sum, $u) {
        assertType('int', $sum);
        assertType('App\User', $u);

        return [$sum + $u->id];
    }, 0);
}
