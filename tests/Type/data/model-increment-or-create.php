<?php

namespace ModelIncrementOrCreate;

use App\User;

use function PHPStan\Testing\assertType;

function test(User $user): void
{
    assertType('App\User', User::query()->incrementOrCreate(['id' => 1], 'id'));
    assertType('App\User', User::incrementOrCreate(['id' => 1], 'id'));
    assertType('App\Account', $user->accounts()->incrementOrCreate(['id' => 1], 'id'));
    assertType('App\Role', $user->roles()->incrementOrCreate(['id' => 1], 'id'));
}
