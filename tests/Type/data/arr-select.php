<?php

namespace ArrSelect;

use App\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  list<array{name: string, role: string, status: string}>        $rows
 * @param  array<string, array{name: string, role: string}>               $keyed
 * @param  array{a: array{name: string, id: int}, b?: array{name: string}} $shape
 * @param  list<User>                                                     $models
 * @param  list<string>                                                   $keys
 * @param  Collection<int, 'name'>                                        $keyCollection
 * @param  list<string>                                                   $strings
 */
function test(
    array $rows,
    array $keyed,
    array $shape,
    array $models,
    array $keys,
    Collection $keyCollection,
    array $strings,
): void {
    assertType('list<array{name: string, role: string}>', Arr::select($rows, ['name', 'role']));
    assertType('list<array{name: string}>', Arr::select($rows, 'name'));
    assertType('list<array{name: string}>', Arr::select($rows, ['name', 'nope']));

    // the keys of the array itself are untouched
    assertType('array<string, array{name: string}>', Arr::select($keyed, ['name']));
    assertType('array{a: array{name: string}, b?: array{name: string}}', Arr::select($shape, ['name']));
    assertType('array{array{a: 1}}', Arr::select([['a' => 1, 'b' => 2]], ['a']));

    // an unknown set of keys can only narrow each key to optional
    assertType('list<array{name?: string, role?: string, status?: string}>', Arr::select($rows, $keys));

    // objects are read through offsetExists()/isset(), so every key is optional
    assertType('list<array{name?: string, email?: string}>', Arr::select($models, ['name', 'email']));

    // items that are neither arrays nor objects are left to the declared return
    // type, as is a collection of keys, which Arr::wrap() makes a key rather
    // than a key set
    assertType('array', Arr::select($strings, ['name']));
    assertType('array', Arr::select($rows, $keyCollection));
}
