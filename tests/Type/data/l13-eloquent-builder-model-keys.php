<?php

declare(strict_types=1);

namespace EloquentBuilderModelKeys;

use App\User;
use App\UuidModel;
use Illuminate\Database\Eloquent\Builder;

use function PHPStan\Testing\assertType;

/**
 * @param Builder<User> $users
 * @param Builder<UuidModel> $uuids
 */
function test(Builder $users, Builder $uuids): void
{
    assertType('list<int>', $users->modelKeys());
    assertType('list<string>', $uuids->modelKeys());
}
