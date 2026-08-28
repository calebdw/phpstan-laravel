<?php

declare(strict_types=1);

namespace ArrGetPull;

use ArrayAccess;
use Illuminate\Support\Arr;

use function PHPStan\Testing\assertType;

/**
 * @param ArrayAccess<string, int> $arrayAccess
 * @param array{foo: 1}|array{bar: 2} $union
 * @param array<string, string> $map
 * @param array{user: array{name: string, email?: string}} $nested
 */
function test(ArrayAccess $arrayAccess, array $union, array $map, array $nested, string|null $nullableKey): void
{
    assertType('1', Arr::get(['foo' => 1], 'foo'));
    assertType('null', Arr::get(['foo' => 1], 'bar'));
    assertType('2', Arr::get(['foo' => 1], 'bar', 2));
    assertType('2|3', Arr::get($union, 'bar', 3));
    assertType('string|null', Arr::get($map, 'foo'));
    assertType('int|null', Arr::get($arrayAccess, 'foo'));
    assertType('array{foo: 1}', Arr::get(['foo' => 1], null));
    assertType('1|array{foo: 1}|null', Arr::get(['foo' => 1], $nullableKey));
    assertType('string', Arr::get($nested, 'user.name'));
    assertType('string|null', Arr::get($nested, 'user.email'));
    assertType('5', Arr::get($nested, 'user.missing', static fn (): int => 5));

    $present = ['foo' => 1];
    $missing = ['foo' => 1];
    $default = ['foo' => 1];

    assertType('1', Arr::pull($present, 'foo'));
    assertType('null', Arr::pull($missing, 'bar'));
    assertType('2', Arr::pull($default, 'bar', 2));
    assertType('2|3', Arr::pull($union, 'bar', 3));
}

/** @param array{foo: 1, bar: 2} $array */
function testPull(array $array): void
{
    assertType('1', Arr::pull($array, 'foo'));
    assertType('array{bar: 2}', $array);
}

/** @param array{0: 'first', 1: 'second'} $array */
function testIntegerPull(array $array): void
{
    assertType("'first'", Arr::pull($array, 0));
    assertType("array{1: 'second'}", $array);
}

/** @param array{user: array{name: string, email: string}, active: bool} $array */
function testNestedPull(array $array): void
{
    assertType('string', Arr::pull($array, 'user.name'));
    assertType('array{user: array{email: string}, active: bool}', $array);
}

/**
 * @param array{foo: 1, bar: 2} $array
 * @param 'foo'|'bar' $key
 */
function testPullUnion(array $array, string $key): void
{
    assertType('1|2', Arr::pull($array, $key));
    assertType('array{bar: 2}|array{foo: 1}', $array);
}
