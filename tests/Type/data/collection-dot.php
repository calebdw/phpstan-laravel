<?php

declare(strict_types=1);

namespace CollectionDot;

use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<string, array{name: string, age: int}>  $rows
 * @param  Collection<string, array{user: array{name: string, id: int}}>  $nested
 * @param  Collection<int, array<string, int>>  $map
 */
function test(Collection $rows, Collection $nested, Collection $map): void
{
    assertType('Illuminate\Support\Collection<string, int|string>', $rows->dot());
    assertType('Illuminate\Support\Collection<string, int|string>', $nested->dot());
    assertType('Illuminate\Support\Collection<string, array{name: string, id: int}>', $nested->dot(1));
    assertType('Illuminate\Support\Collection<string, int>', $map->dot());
}
