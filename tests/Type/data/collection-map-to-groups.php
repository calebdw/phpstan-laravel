<?php

declare(strict_types=1);

namespace CollectionMapToGroups;

use App\Transaction;
use App\TransactionCollection;
use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  EloquentCollection<int, User>  $users
 * @param  Collection<int, array{type: string, n: int}>  $rows
 * @param  LazyCollection<int, User>  $lazyUsers
 * @param  TransactionCollection<int, Transaction>  $transactions
 */
function test(
    EloquentCollection $users,
    Collection $rows,
    LazyCollection $lazyUsers,
    TransactionCollection $transactions,
): void {
    // A literal key is the only group that callback can produce, so it stays
    // a literal. Eloquent map() then toBase()s the outer collection.
    assertType(
        "Illuminate\Support\Collection<'foo', Illuminate\Database\Eloquent\Collection<int, int>>",
        $users->mapToGroups(fn (User $user): array => ['foo' => $user->id]),
    );

    // Dynamic keys are just the column type. Group values keep the models.
    assertType(
        'Illuminate\Support\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>',
        $users->mapToGroups(fn ($u) => [$u->email => $u]),
    );
    assertType(
        'Illuminate\Support\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>',
        $users->mapToGroups(fn ($u) => [$u->id => $u]),
    );

    // Support collections keep their class on both levels.
    assertType(
        'Illuminate\Support\Collection<string, Illuminate\Support\Collection<int, int>>',
        $rows->mapToGroups(fn ($row) => [$row['type'] => $row['n']]),
    );
    assertType(
        "Illuminate\Support\Collection<'x', Illuminate\Support\Collection<int, int>>",
        $rows->mapToGroups(fn ($row): array => ['x' => $row['n']]),
    );

    // Lazy collections keep theirs too.
    assertType(
        'Illuminate\Support\LazyCollection<string, Illuminate\Support\LazyCollection<int, App\User>>',
        $lazyUsers->mapToGroups(fn ($u) => [$u->email => $u]),
    );

    // Custom Eloquent collections: outer still toBase()s, inner is make().
    assertType(
        'Illuminate\Support\Collection<int, App\TransactionCollection<int, App\Transaction>>',
        $transactions->mapToGroups(fn (Transaction $t) => [$t->id => $t]),
    );

    // Branching literals union the keys the same way groupBy does.
    assertType(
        "Illuminate\Support\Collection<'hi'|'lo', Illuminate\Database\Eloquent\Collection<int, int>>",
        $users->mapToGroups(fn (User $u): array => $u->id > 1 ? ['hi' => $u->id] : ['lo' => $u->id]),
    );

    // Runtime only reads the first pair, so a second key in the same array
    // does not become a group.
    assertType(
        "Illuminate\Support\Collection<'foo', Illuminate\Database\Eloquent\Collection<int, int>>",
        $users->mapToGroups(fn (User $u): array => ['foo' => $u->id, 'bar' => $u->email]),
    );

    // mapToDictionary is the same pair, but the values stay arrays. Eloquent
    // keeps its class because it is new static(), not map()->toBase().
    assertType(
        'Illuminate\Database\Eloquent\Collection<string, array<int, App\User>>',
        $users->mapToDictionary(fn ($u) => [$u->email => $u]),
    );
    assertType(
        "Illuminate\Database\Eloquent\Collection<'foo', array<int, int>>",
        $users->mapToDictionary(fn (User $u): array => ['foo' => $u->id]),
    );
    assertType(
        'Illuminate\Support\Collection<string, array<int, int>>',
        $rows->mapToDictionary(fn ($row) => [$row['type'] => $row['n']]),
    );
}
