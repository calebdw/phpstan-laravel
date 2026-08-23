<?php

declare(strict_types=1);

namespace GroupBy;

use App\Post;
use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Stringable;

use function PHPStan\Testing\assertType;

/**
 * @param  EloquentCollection<int, User>  $users
 * @param  EloquentCollection<int, Post>  $posts
 * @param  Collection<string, User>  $keyed
 */
function test(
    EloquentCollection $users,
    EloquentCollection $posts,
    Collection $keyed,
): void {
    // One grouper, key resolved from the column.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy('name'));
    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy('id'));

    // Two and three groupers nest one level each.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>>', $users->groupBy(['name', 'id']));
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>>>', $users->groupBy(['name', 'id', 'email']));

    // Dotted access through a relation.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\Post>>', $posts->groupBy('user.name'));

    // Callables, including ones declaring no types.
    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(fn ($u) => $u->id));
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(function ($u) {
        return $u->name;
    }));

    // A bool group key is cast to int, a Stringable to string.
    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(fn ($u) => $u->id > 5));
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(fn ($u): Stringable => str($u->name)));

    // A grouper returning an array files the item under several keys.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(fn ($u): array => [$u->name]));

    // Mixing a column and a callable across levels.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>>', $users->groupBy(['name', fn ($u) => $u->id]));

    // preserveKeys keeps the original keys on the innermost collection.
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\User>>', $keyed->groupBy('id'));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<string, App\User>>', $keyed->groupBy('id', true));
}
