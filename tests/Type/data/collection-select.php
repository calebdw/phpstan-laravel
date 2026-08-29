<?php

namespace CollectionSelect;

use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function collect;
use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, array{name: string, role: string, status: string}>     $users
 * @param  LazyCollection<int, array{name: string, role: string, status: string}> $lazyUsers
 * @param  Collection<int, array{name: string, role?: string}>                    $partialUsers
 * @param  Collection<int, array{string, string, string}>                         $rows
 * @param  EloquentCollection<int, User>                                          $models
 * @param  Collection<int, string>                                                $strings
 * @param  Collection<int, 'name'|'role'>                                         $keys
 * @param  list<string>                                                           $keyList
 */
function test(
    Collection $users,
    LazyCollection $lazyUsers,
    Collection $partialUsers,
    Collection $rows,
    EloquentCollection $models,
    Collection $strings,
    Collection $keys,
    array $keyList,
): void {
    assertType('Illuminate\Support\Collection<int, array{name: string, role: string}>', $users->select(['name', 'role']));
    assertType('Illuminate\Support\Collection<int, array{name: string}>', $users->select('name'));
    assertType('Illuminate\Support\LazyCollection<int, array{name: string}>', $lazyUsers->select(['name']));

    // null selects every key
    assertType('Illuminate\Support\Collection<int, array{name: string, role: string, status: string}>', $users->select(null));

    // keys missing from the items are dropped, and optional keys stay optional
    assertType('Illuminate\Support\Collection<int, array{name: string}>', $users->select(['name', 'nope']));
    assertType('Illuminate\Support\Collection<int, array{name: string, role?: string}>', $partialUsers->select(['name', 'role']));

    // the keys are looked up on the items, so integers are valid too
    assertType('Illuminate\Support\Collection<int, array{0: string, 2: string}>', $rows->select([0, 2]));

    // an unknown set of keys can only narrow each key to optional
    assertType('Illuminate\Support\Collection<int, array{name?: string, role?: string}>', $users->select($keys));
    assertType('Illuminate\Support\Collection<int, array{name?: string, role?: string, status?: string}>', $users->select($keyList));

    // objects are read through offsetExists()/isset(), so every key is optional
    assertType('Illuminate\Database\Eloquent\Collection<int, array{name?: string, email?: string}>', $models->select(['name', 'email']));
    assertType('Illuminate\Database\Eloquent\Collection<int, array{name?: string, nope?: mixed}>', $models->select(['name', 'nope']));
    assertType('Illuminate\Database\Eloquent\Collection<int, array<string, mixed>>', $models->select($keyList));

    // items that are neither arrays nor objects are left to the declared return type
    assertType('Illuminate\Support\Collection<int, string>', $strings->select(['name']));

    assertType(
        'Illuminate\Support\Collection<int, array{name: string, role: string}>',
        collect([['name' => 'Taylor', 'role' => 'Developer', 'status' => 'active']])->select(['name', 'role']),
    );
}
