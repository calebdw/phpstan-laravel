<?php

declare(strict_types=1);

namespace ModelCounterMethods;

use App\User;

use function PHPStan\Testing\assertType;

function test(User $user): void
{
    assertType('int', $user->incrementEach(['counter' => 1]));
    assertType('int', $user->incrementEachQuietly(['counter' => 1]));
    assertType('int', $user->decrementEach(['counter' => 1]));
    assertType('int', $user->decrementEachQuietly(['counter' => 1]));
}
