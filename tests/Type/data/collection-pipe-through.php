<?php

declare(strict_types=1);

namespace CollectionPipeThrough;

use App\User;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/** @param Collection<int, User> $users */
function test(Collection $users): void
{
    assertType('string', $users->pipeThrough([
        fn ($c) => $c->pluck('name'),
        fn ($c) => $c->join(', '),
    ]));

    assertType('Illuminate\Support\Collection<int, string>', $users->pipeThrough([
        fn ($c) => $c->pluck('email'),
    ]));

    assertType('Illuminate\Support\Collection<int, App\User>', $users->pipeThrough([]));
}
