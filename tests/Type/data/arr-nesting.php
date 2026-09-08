<?php

declare(strict_types=1);

namespace ArrNesting;

use App\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  list<list<User>>  $nested
 * @param  list<array<string, int>>  $arrays
 * @param  list<Collection<int, User>>  $collections
 * @param  array<string, array{name: string, age: int}>  $rows
 * @param  array<string, array{user: array{name: string, id: int}}>  $nestedRows
 */
function test(array $nested, array $arrays, array $collections, array $rows, array $nestedRows): void
{
    assertType('list<App\User>', Arr::collapse($nested));
    assertType('list<int>', Arr::collapse($arrays));
    assertType('list<App\User>', Arr::collapse($collections));

    assertType('list<App\User>', Arr::flatten($nested));
    assertType('list<App\User>', Arr::flatten($nested, 1));
    assertType('list<int>', Arr::flatten($arrays));

    assertType('array<string, int|string>', Arr::dot($rows));
    assertType('array<string, int|string>', Arr::dot($nestedRows));
    assertType('array<string, array{name: string, id: int}>', Arr::dot($nestedRows, depth: 1));
}
