<?php

declare(strict_types=1);

namespace ArrMap;

use App\User;
use Illuminate\Support\Arr;

use function PHPStan\Testing\assertType;

/**
 * @param  array<string, int>  $map
 * @param  list<User>  $users
 */
function test(array $map, array $users): void
{
    assertType('array<string, decimal-int-string>', Arr::map($map, fn ($v) => (string) $v));
    assertType('list<int>', Arr::map($users, fn ($u) => $u->id));
    assertType('array<string, App\User>', Arr::mapWithKeys($users, fn ($u) => [$u->email => $u]));
}
