<?php

declare(strict_types=1);

namespace CollectionCountBy;

use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

enum CountKey: int
{
    case First  = 10;
    case Second = 20;
}

/**
 * @param  EloquentCollection<int, User>  $users
 * @param  Collection<int, array{name: string, role: string}>  $rows
 * @param  Collection<string, int>  $items
 * @param  LazyCollection<int, User>  $lazyUsers
 */
function test(
    EloquentCollection $users,
    Collection $rows,
    Collection $items,
    LazyCollection $lazyUsers,
): void {
    // Column and callback keys resolve the same way as groupBy.
    assertType('Illuminate\Support\Collection<string, int>', $users->countBy('email'));
    assertType('Illuminate\Support\Collection<int, int>', $users->countBy('id'));
    assertType('Illuminate\Support\Collection<string, int>', $rows->countBy('name'));
    assertType('Illuminate\Support\LazyCollection<string, int>', $lazyUsers->countBy('email'));

    assertType('Illuminate\Support\Collection<int, int>', $users->countBy(fn ($u) => $u->id));
    assertType('Illuminate\Support\Collection<string, int>', $users->countBy(function ($u) {
        return $u->email;
    }));

    // No argument counts by the values themselves.
    assertType('Illuminate\Support\Collection<int, int>', $items->countBy());
}

/**
 * @param  Collection<int, array{key: CountKey}>  $items
 */
function testEnumKeys(Collection $items): void
{
    assertType('Illuminate\Support\Collection<10|20, int>', $items->countBy(fn ($i) => $i['key']->value));
}
