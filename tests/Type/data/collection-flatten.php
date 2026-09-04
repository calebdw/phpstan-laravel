<?php

declare(strict_types=1);

namespace CollectionFlatten;

use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, Collection<int, User>>  $nested
 * @param  Collection<int, Collection<int, Collection<int, User>>>  $deep
 * @param  Collection<int, array<string, int>>  $arrays
 * @param  Collection<int, array<string, array<string, bool>>>  $nestedArrays
 * @param  EloquentCollection<int, EloquentCollection<int, User>>  $eloquent
 * @param  LazyCollection<int, LazyCollection<int, User>>  $lazy
 */
function test(
    Collection $nested,
    Collection $deep,
    Collection $arrays,
    Collection $nestedArrays,
    EloquentCollection $eloquent,
    LazyCollection $lazy,
): void {
    assertType('Illuminate\Support\Collection<int, App\User>', $nested->flatten());
    assertType('Illuminate\Support\Collection<int, App\User>', $nested->flatten(1));
    assertType('Illuminate\Support\Collection<int, App\User>', $deep->flatten());
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\User>>', $deep->flatten(1));
    assertType('Illuminate\Support\Collection<int, App\User>', $deep->flatten(2));

    assertType('Illuminate\Support\Collection<int, int>', $arrays->flatten());
    assertType('Illuminate\Support\Collection<int, int>', $arrays->flatten(1));
    assertType('Illuminate\Support\Collection<int, array<string, bool>>', $nestedArrays->flatten(1));
    assertType('Illuminate\Support\Collection<int, bool>', $nestedArrays->flatten(2));

    assertType('Illuminate\Support\Collection<int, App\User>', $eloquent->flatten());
    assertType('Illuminate\Support\LazyCollection<int, App\User>', $lazy->flatten());
}
