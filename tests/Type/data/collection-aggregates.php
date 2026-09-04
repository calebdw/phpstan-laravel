<?php

declare(strict_types=1);

namespace CollectionAggregates;

use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  EloquentCollection<int, User>  $users
 * @param  Collection<int, array{price: int, name: string}>  $rows
 */
function test(EloquentCollection $users, Collection $rows): void
{
    assertType('int', $users->sum('id'));
    assertType('int', $rows->sum('price'));
    assertType('int', $users->sum(fn ($u) => $u->id));

    assertType('int|null', $users->min('id'));
    assertType('string|null', $users->max('email'));
    assertType('int|null', $rows->min('price'));
    assertType('string|null', $rows->max('name'));
    assertType('int|null', $users->min(fn ($u) => $u->id));
}
