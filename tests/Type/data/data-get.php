<?php

declare(strict_types=1);

namespace DataGet;

use App\User;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  array{user: array{name: string, email?: string}}  $nested
 * @param  array<string, int>  $map
 * @param  list<array{name: string}>  $rows
 * @param  User  $user
 * @param  Collection<int, User>  $users
 * @param  'user.name'|'user.email'  $path
 */
function test(
    array $nested,
    array $map,
    array $rows,
    User $user,
    Collection $users,
    string $path,
): void {
    assertType('array{user: array{name: string, email?: string}}', data_get($nested, null));
    assertType('string', data_get($nested, 'user.name'));
    assertType('string', data_get($nested, 'user.email'));
    assertType('5', data_get($nested, 'user.missing', 5));
    assertType('5', data_get($nested, 'user.missing', static fn () => 5));
    assertType('string', data_get($nested, ['user', 'name']));
    assertType('string', data_get($nested, $path));
    assertType('int', data_get($map, 'foo'));

    assertType('list<string>', data_get($rows, '*.name'));
    assertType('string', data_get($user, 'name'));
    assertType('list<string>', data_get($users, '*.name'));
}
