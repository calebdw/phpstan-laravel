<?php

declare(strict_types=1);

namespace CollectionCollapse;

use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, Collection<int, User>>  $nested
 * @param  Collection<int, Collection<string, User>>  $keyed
 * @param  Collection<int, array<string, int>>  $arrays
 * @param  EloquentCollection<int, EloquentCollection<int, User>>  $eloquent
 * @param  LazyCollection<int, LazyCollection<int, User>>  $lazy
 */
function test(
    Collection $nested,
    Collection $keyed,
    Collection $arrays,
    EloquentCollection $eloquent,
    LazyCollection $lazy,
): void {
    assertType('Illuminate\Support\Collection<int, App\User>', $nested->collapse());
    assertType('Illuminate\Support\Collection<int, App\User>', $keyed->collapse());
    assertType('Illuminate\Support\Collection<int, int>', $arrays->collapse());
    assertType('Illuminate\Support\Collection<int, App\User>', $eloquent->collapse());
    assertType('Illuminate\Support\LazyCollection<int, App\User>', $lazy->collapse());

    assertType('Illuminate\Support\Collection<int, App\User>', $nested->collapseWithKeys());
    assertType('Illuminate\Support\Collection<string, App\User>', $keyed->collapseWithKeys());
    assertType('Illuminate\Support\Collection<string, int>', $arrays->collapseWithKeys());
}
