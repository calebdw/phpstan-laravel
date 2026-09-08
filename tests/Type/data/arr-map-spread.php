<?php

declare(strict_types=1);

namespace ArrMapSpread;

use App\User;
use Illuminate\Support\Arr;

use function PHPStan\Testing\assertType;

/**
 * @param  array<int, array{int, int}>  $pairs
 * @param  array<int, array{0: User, 1: string}>  $users
 */
function test(array $pairs, array $users): void
{
    assertType('array<int, int>', Arr::mapSpread($pairs, fn ($a, $b) => $a + $b));

    Arr::mapSpread($pairs, function ($a, $b, $key) {
        assertType('int', $a);
        assertType('int', $b);
        assertType('int', $key);

        return $a + $b;
    });

    Arr::mapSpread($users, function ($u, $email) {
        assertType('App\User', $u);
        assertType('string', $email);

        return $u;
    });

    assertType('array<int, App\User>', Arr::mapSpread($users, fn ($u, $email) => $u));
}
