<?php

declare(strict_types=1);

namespace CollectionStructure;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, array{id: int, name: string}>  $rows
 * @param  Collection<int, array{id: int, name: string}>  $more
 * @param  LazyCollection<int, array{id: int, name: string}>  $lazyRows
 */
function test(Collection $rows, Collection $more, LazyCollection $lazyRows): void
{
    // Added values widen their literals but keep their keys, so a matching
    // shape is not absorbed into non-empty-array<string, int|string>.
    assertType('Illuminate\Support\Collection<int, array{id: int, name: string}>', $rows->concat($more));
    assertType('Illuminate\Support\Collection<int, array{id: int, name: string}>', $rows->concat([['id' => 1, 'name' => 'x']]));
    assertType('Illuminate\Support\Collection<int, array{id: int, name?: string}>', $rows->concat([['id' => 1]]));
    assertType('Illuminate\Support\LazyCollection<int, array{id: int, name: string}>', $lazyRows->concat($more));
    assertType('Illuminate\Support\Collection<int, array{id: int, name: string}>', $rows->pad(5, ['id' => 0, 'name' => '']));
    assertType(
        'Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, array{id: int, name: string}|null>>',
        $rows->zip($more),
    );
}
