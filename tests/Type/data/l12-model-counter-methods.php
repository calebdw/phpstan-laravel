<?php

declare(strict_types=1);

namespace ModelCounterMethods;

use App\Account;
use App\User;

use function PHPStan\Testing\assertType;

function test(User $user): void
{
    assertType('int', $user->increment('counter'));
    assertType('int', $user->incrementQuietly('counter'));
    assertType('int', $user->decrement('counter'));
    assertType('int', $user->decrementQuietly('counter'));
}

function unionReceiver(User|Account $model): void
{
    assertType('int', $model->increment('counter'));
    assertType('int', $model->incrementQuietly('counter'));
    assertType('int', $model->decrement('counter'));
    assertType('int', $model->decrementQuietly('counter'));
}
