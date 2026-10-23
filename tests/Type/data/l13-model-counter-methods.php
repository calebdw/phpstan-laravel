<?php

declare(strict_types=1);

namespace ModelCounterMethods;

use App\Account;
use App\User;

use function PHPStan\Testing\assertType;

function test(User $user): void
{
    assertType('int|false', $user->increment('counter'));
    assertType('int', $user->incrementQuietly('counter'));
    assertType('int|false', $user->decrement('counter'));
    assertType('int', $user->decrementQuietly('counter'));
    assertType('int|false', $user->incrementEach(['counter' => 1]));
    assertType('int', $user->incrementEachQuietly(['counter' => 1]));
    assertType('int|false', $user->decrementEach(['counter' => 1]));
    assertType('int', $user->decrementEachQuietly(['counter' => 1]));
}

function unionReceiver(User|Account $model): void
{
    assertType('int|false', $model->increment('counter'));
    assertType('int', $model->incrementQuietly('counter'));
    assertType('int|false', $model->decrement('counter'));
    assertType('int', $model->decrementQuietly('counter'));
}
