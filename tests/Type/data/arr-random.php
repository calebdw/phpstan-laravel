<?php

declare(strict_types=1);

namespace ArrRandom;

use App\User;
use Illuminate\Support\Arr;

use function PHPStan\Testing\assertType;

/**
 * @param  array<string, User>  $users
 * @param  list<int>  $ids
 */
function test(array $users, array $ids): void
{
    assertType('App\User', Arr::random($users));
    assertType('list<App\User>', Arr::random($users, 2));
    assertType('array<string, App\User>', Arr::random($users, 2, true));
    assertType('int', Arr::random($ids));
    assertType('list<int>', Arr::random($ids, 1));
    assertType('App\User', Arr::sole($users));
    assertType('int', Arr::sole($ids, fn ($id) => $id > 0));
}
