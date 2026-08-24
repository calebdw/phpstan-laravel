<?php

declare(strict_types=1);

namespace KeyBy;

use App\Post;
use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  EloquentCollection<int, User>  $users
 * @param  Collection<int, array{user: array{id: int, name: string}}>  $arrays
 * @param  EloquentCollection<int, Post>  $posts
 * @param  LazyCollection<int, User>  $lazyUsers
 * @param  array<int, User>  $userArray
 * @param  list<array{user: array{id: int, name: string}}>  $list
 */
function test(
    EloquentCollection $users,
    Collection $arrays,
    EloquentCollection $posts,
    LazyCollection $lazyUsers,
    array $userArray,
    array $list,
): void {
    // keyBy rewrites the keys and keeps both the value type and the class.
    assertType('Illuminate\Database\Eloquent\Collection<string, App\User>', $users->keyBy('name'));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $users->keyBy('id'));
    assertType('Illuminate\Support\LazyCollection<string, App\User>', $lazyUsers->keyBy('name'));

    // Callbacks, including ones that declare no types at all.
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $users->keyBy(fn ($u) => $u->id));
    assertType('Illuminate\Database\Eloquent\Collection<string, App\User>', $users->keyBy(function ($u) {
        assertType('App\User', $u);

        return $u->name;
    }));

    // Nested access, by dotted string and by array of segments.
    assertType('Illuminate\Database\Eloquent\Collection<string, App\Post>', $posts->keyBy('user.name'));
    assertType('Illuminate\Database\Eloquent\Collection<string, App\Post>', $posts->keyBy(['user', 'name']));
    assertType('Illuminate\Support\Collection<string, array{user: array{id: int, name: string}}>', $arrays->keyBy('user.name'));

    // Arr::keyBy keeps the value type too.
    assertType('array<string, App\User>', Arr::keyBy($userArray, 'name'));
    assertType('array<int, App\User>', Arr::keyBy($userArray, fn ($u) => $u->id));
    assertType('array<string, array{user: array{id: int, name: string}}>', Arr::keyBy($list, 'user.name'));
}

/**
 * PHP casts a null key to an empty string on the way into the array, so a
 * nullable column contributes '' rather than null. Leaving the null in place
 * produces a TKey outside the array-key bound, which PHPStan then reports as
 * a type not matching itself.
 *
 * @param  Collection<int, array{id: int, parent_id: int|null}>  $rows
 */
function testNullableKey(Collection $rows): void
{
    assertType("Illuminate\\Support\\Collection<''|int, array{id: int, parent_id: int|null}>", $rows->keyBy('parent_id'));
    assertType('Illuminate\\Support\\Collection<int, array{id: int, parent_id: int|null}>', $rows->keyBy('id'));

    // Grouping casts the same way.
    assertType("Illuminate\\Support\\Collection<''|int, Illuminate\\Support\\Collection<int, array{id: int, parent_id: int|null}>>", $rows->groupBy('parent_id'));
}

/**
 * The array helpers cast identically, since the reason is PHP's array keys
 * rather than anything Collection does.
 *
 * @param  list<array{id: int, parent_id: int|null}>  $rows
 */
function testNullableKeyOnArrays(array $rows): void
{
    assertType("array<''|int, array{id: int, parent_id: int|null}>", Arr::keyBy($rows, 'parent_id'));
    assertType("array<''|int, int>", Arr::pluck($rows, 'id', 'parent_id'));
}
