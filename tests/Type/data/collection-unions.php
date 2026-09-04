<?php

declare(strict_types=1);

namespace CollectionUnions;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, User>|LazyCollection<int, User>  $either
 */
function test(Collection|LazyCollection $either): void
{
    assertType(
        'Illuminate\Support\Collection<string, App\User>|Illuminate\Support\LazyCollection<string, App\User>',
        $either->keyBy('email'),
    );
    assertType(
        'Illuminate\Support\Collection<string, Illuminate\Support\Collection<int, App\User>>|Illuminate\Support\LazyCollection<string, Illuminate\Support\Collection<int, App\User>>',
        $either->groupBy('email'),
    );
    assertType(
        'Illuminate\Support\Collection<int, string>|Illuminate\Support\LazyCollection<int, string>',
        $either->pluck('name'),
    );
}
