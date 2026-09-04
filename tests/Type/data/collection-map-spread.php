<?php

declare(strict_types=1);

namespace CollectionMapSpread;

use App\User;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, array{int, int}>  $pairs
 * @param  Collection<int, array{0: User, 1: string}>  $users
 */
function test(Collection $pairs, Collection $users): void
{
    assertType('Illuminate\Support\Collection<int, int>', $pairs->mapSpread(fn ($a, $b) => $a + $b));

    $pairs->mapSpread(function ($a, $b, $key) {
        assertType('int', $a);
        assertType('int', $b);
        assertType('int', $key);

        return $a + $b;
    });

    $users->mapSpread(function ($u, $email) {
        assertType('App\User', $u);
        assertType('string', $email);

        return $u;
    });

    assertType('Illuminate\Support\Collection<int, App\User>', $users->mapSpread(fn ($u, $email) => $u));
}
