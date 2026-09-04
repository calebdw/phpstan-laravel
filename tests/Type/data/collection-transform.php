<?php

declare(strict_types=1);

namespace CollectionTransform;

use App\User;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/** @param Collection<int, User> $users */
function test(Collection $users): void
{
    $users->transform(fn (User $u) => $u->email);
    assertType('Illuminate\Support\Collection<int, string>', $users);

    $ints = collect([1, 2, 3]);
    $ints->transform(fn (int $i) => (string) $i);
    assertType('Illuminate\Support\Collection<int, decimal-int-string>', $ints);
}
