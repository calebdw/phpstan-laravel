<?php

declare(strict_types=1);

namespace ArrExcept;

use Illuminate\Support\Arr;

use function PHPStan\Testing\assertType;

/**
 * @param  array{id: int, name: string, email: string}  $shape
 * @param  array{user: array{name: string, email: string}, active: bool}  $nested
 * @param  array{id: int, name?: string}  $optional
 */
function test(array $shape, array $nested, array $optional): void
{
    assertType('array{name: string, email: string}', Arr::except($shape, 'id'));
    assertType('array{email: string}', Arr::except($shape, ['id', 'name']));
    assertType('array{id: int, name: string, email: string}', Arr::except($shape, []));
    assertType('array{id: int, name?: string}', Arr::except($optional, 'email'));

    assertType('array{user: array{email: string}, active: bool}', Arr::except($nested, 'user.name'));
    assertType('array{active: bool}', Arr::except($nested, 'user'));
}
