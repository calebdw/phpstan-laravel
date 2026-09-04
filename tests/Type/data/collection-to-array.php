<?php

declare(strict_types=1);

namespace CollectionToArray;

use App\User;
use Arrayable\Foo;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, int>  $ints
 * @param  EloquentCollection<int, User>  $users
 * @param  Collection<int, Collection<string, User>>  $nested
 * @param  Collection<string, Foo>  $foos
 * @param  LazyCollection<int, User>  $lazy
 */
function test(
    Collection $ints,
    EloquentCollection $users,
    Collection $nested,
    Collection $foos,
    LazyCollection $lazy,
): void {
    assertType('array<int, int>', $ints->toArray());
    assertType('array<int, array<string, mixed>>', $users->toArray());
    assertType('array<int, array<string, array<string, mixed>>>', $nested->toArray());
    assertType('array<string, array<string, int>>', $foos->toArray());
    assertType('array<int, array<string, mixed>>', $lazy->toArray());

    assertType('array<int, int>', $ints->jsonSerialize());
    assertType('array<int, array<string, mixed>>', $users->jsonSerialize());
    assertType('array<int, array<string, array<string, mixed>>>', $nested->jsonSerialize());
}

/**
 * @phpstan-type Nested array<int, Nested>
 * @param  Collection<int, Nested>  $recursive
 */
function testRecursive(Collection $recursive): void
{
    $recursive->toArray();
}
