<?php

namespace FillAndInsert;

use App\User;

use function PHPStan\Testing\assertType;

function insert(User $user): void
{
    assertType('bool', User::query()->fillAndInsert([['name' => 'A']]));
    assertType('int', User::query()->fillAndInsertOrIgnore([['name' => 'A']]));
    assertType('int', User::fillAndInsertGetId(['name' => 'A']));
    assertType('array<int, array<string, mixed>>', $user->accounts()->fillForInsert([['name' => 'A']]));
}
