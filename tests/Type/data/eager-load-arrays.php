<?php

namespace EagerLoadArrays;

use App\User;
use Illuminate\Database\Eloquent\Collection;

use function PHPStan\Testing\assertType;

/** @param Collection<int, User> $users */
function test(Collection $users): void
{
    User::query()->with(['accounts' => fn ($q) => assertType('mixed', $q)]);
    User::query()->withOnly(['accounts' => fn ($q) => assertType('mixed', $q)]);
    $users->load(['accounts' => fn ($q) => assertType('mixed', $q)]);
    $users->loadMissing(['accounts' => fn ($q) => assertType('mixed', $q)]);
}
