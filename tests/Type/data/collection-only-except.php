<?php

declare(strict_types=1);

namespace CollectionOnlyExcept;

use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<'id'|'name'|'email', int>  $keyed
 * @param  Collection<string, User>  $users
 * @param  EloquentCollection<int, User>  $models
 */
function test(Collection $keyed, Collection $users, EloquentCollection $models): void
{
    assertType("Illuminate\Support\Collection<'id'|'name', int>", $keyed->only(['id', 'name']));
    assertType("Illuminate\Support\Collection<'id', int>", $keyed->only('id'));
    assertType("Illuminate\Support\Collection<'email', int>", $keyed->except(['id', 'name']));

    assertType("Illuminate\Support\Collection<'name', App\User>", $users->only(['name']));

    // Eloquent only/except filter by model key and reindex, so the collection
    // type is unchanged.
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $models->only([1, 2]));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $models->except([1]));
}
