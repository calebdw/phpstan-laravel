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
 * @param  Collection<int, int>  $ints
 * @param  Collection<int, float>  $floats
 * @param  Collection<int, string>  $names
 * @param  Collection<int, float|int>  $numbers
 */
function test(
    EloquentCollection $users,
    Collection $rows,
    Collection $ints,
    Collection $floats,
    Collection $names,
    Collection $numbers,
): void {
    assertType('int', $ints->sum());
    assertType('float|int', $floats->sum());
    assertType('float|int', $numbers->sum());
    assertType('int', $users->sum('id'));
    assertType('int', $rows->sum('price'));
    assertType('int', $users->sum(fn ($u) => $u->id));

    assertType('int|null', $users->min('id'));
    assertType('string|null', $users->max('email'));
    assertType('int|null', $rows->min('price'));
    assertType('string|null', $rows->max('name'));
    assertType('int|null', $users->min(fn ($u) => $u->id));

    assertType('float|int|null', $ints->avg());
    assertType('float|int|null', $ints->average());
    assertType('float|null', $floats->avg());
    assertType('float|int|null', $users->avg('id'));
    assertType('float|int|null', $users->avg(fn ($u) => $u->id));
    assertType('float|int|null', $rows->average('price'));

    assertType('float|int|null', $ints->median());
    assertType('float|null', $floats->median());
    assertType('float|int|null', $users->median('id'));
    assertType('float|int|null', $rows->median('price'));

    assertType('list<int>|null', $ints->mode());
    assertType('list<string>|null', $names->mode());
    assertType('list<int>|null', $users->mode('id'));
    assertType('list<string>|null', $users->mode('email'));
    assertType('list<string>|null', $rows->mode('name'));
}
