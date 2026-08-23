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
