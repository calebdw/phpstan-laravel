<?php

declare(strict_types=1);

namespace ArrOnly;

use App\User;
use Illuminate\Support\Arr;

use function PHPStan\Testing\assertType;

/**
 * @param  array{id: int, name: string, email: string}  $shape
 * @param  array{id: int, name?: string}                $optional
 * @param  array{1: string, foo: string}                $numericKeys
 * @param  array{user: User}                            $objects
 * @param  array<string, int>                           $map
 * @param  list<string>                                 $list
 * @param  list<string>                                 $keys
 */
function test(
    array $shape,
    array $optional,
    array $numericKeys,
    array $objects,
    array $map,
    array $list,
    array $keys,
    string $key,
): void {
    assertType('array{id: int, name: string}', Arr::only($shape, ['id', 'name']));
    assertType('array{id: int, name: string, email: string}', Arr::only($shape, ['id', 'name', 'email']));
    assertType('array{}', Arr::only($shape, []));

    // A single key does not have to be wrapped, the framework casts it to an array.
    assertType('array{id: int}', Arr::only($shape, 'id'));

    // Keys that the array does not have are dropped rather than added as null,
    // which is where Arr::only parts ways with Model::only.
    assertType('array{id: int}', Arr::only($shape, ['id', 'nope']));
    assertType('array{}', Arr::only($shape, ['nope']));

    // Arr::only is a flat key intersection, so a dot never traverses.
    assertType('array{}', Arr::only($objects, ['user.name']));
    assertType('array{user: App\User}', Arr::only($objects, ['user']));

    // An optional key stays optional instead of being promoted to required.
    assertType('array{id: int, name?: string}', Arr::only($optional, ['id', 'name']));
    assertType('array{name?: string}', Arr::only($optional, ['name']));

    // array_flip casts an integer-like string key to an int, the same as PHP does.
    assertType('array{1: string}', Arr::only($numericKeys, ['1']));
    assertType('array{1: string}', Arr::only($numericKeys, [1]));

    // Without a known shape to intersect, the requested keys still narrow the key type.
    assertType("array<'a'|'b', int>", Arr::only($map, ['a', 'b']));
    assertType('array<0|2, string>', Arr::only($list, [0, 2]));

    // Arr::only can only drop entries, so unknown keys leave every entry optional.
    assertType('array{id?: int, name?: string, email?: string}', Arr::only($shape, $keys));
    assertType('array{id?: int, name?: string, email?: string}', Arr::only($shape, [$key]));
    assertType('array{id?: int, name?: string, email?: string}', Arr::only($shape, $key));
    assertType('array<string, int>', Arr::only($map, $keys));

    assertType('array{id: int, name: string}', Arr::only(array: $shape, keys: ['id', 'name']));
}
