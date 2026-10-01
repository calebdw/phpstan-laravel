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
 * @param  list<array{email?: string}>  $contacts
 * @param  array{a: list<array{b?: list<int>, c?: int}>}  $deep
 * @param  Collection<string, int|null>  $nullables
 * @param  array{a: list<int>|null}  $maybeList
 * @param  User  $user
 * @param  Collection<int, User>  $users
 * @param  'user.name'|'user.email'  $path
 * @param  list<string>  $names
 * @param  Collection<int, string>  $nameCollection
 * @param  Collection<string, int>  $stats
 * @param  array{items: list<array{name: string}>}  $items
 * @param  'name'|'email'  $field
 */
function test(
    array $nested,
    array $map,
    array $rows,
    array $contacts,
    array $deep,
    Collection $nullables,
    array $maybeList,
    User $user,
    Collection $users,
    string $path,
    array $names,
    Collection $nameCollection,
    Collection $stats,
    array $items,
    string $field,
    string|null $maybeKey,
    Dto $dto,
): void {
    assertType('array{user: array{name: string, email?: string}}', data_get($nested, null));
    assertType('string', data_get($nested, 'user.name'));
    assertType('string|null', data_get($nested, 'user.email'));
    assertType('5|string', data_get($nested, 'user.email', 5));
    assertType('5', data_get($nested, 'user.missing', 5));
    assertType('5', data_get($nested, 'user.missing', static fn () => 5));
    assertType('string', data_get($nested, ['user', 'name']));
    assertType('string|null', data_get($nested, $path));
    assertType('int|null', data_get($map, 'foo'));

    assertType('list<string>', data_get($rows, '*.name'));
    assertType('list<string|null>', data_get($contacts, '*.email', 5));
    assertType('string', data_get($user, 'name'));
    assertType('int|null', data_get($user, 'nullable_year'));
    assertType("'none'|int", data_get($user, 'nullable_year', 'none'));
    assertType('list<string>', data_get($users, '*.name'));

    assertType('list<int>', data_get($deep, 'a.*.b.*'));
    assertType("'d'|int|null", data_get($nullables, 'foo', 'd'));
    assertType('list<int>|null', data_get($maybeList, 'a.*'));

    assertType('string|null', data_get($names, '0'));
    assertType('string|null', data_get($names, 0));
    assertType('string|null', data_get($nameCollection, '0'));
    assertType('int|null', data_get($stats, 'map'));
    assertType('string|null', data_get($nested, ['user', $field]));
    assertType('string|null', data_get($items, ['items', 0, 'name']));
    assertType("'time'", data_get($nested, 'user.missing', 'time'));
    assertType('mixed', data_get($nested, $maybeKey));
    assertType('string', data_get($dto, 'name'));
}

class Dto
{
    public string $name = 'x';
}
