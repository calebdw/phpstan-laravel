<?php

declare(strict_types=1);

namespace CollectionMethodCorrectness;

use App\Transaction;
use App\TransactionCollection;
use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  EloquentCollection<int, User>  $users
 * @param  EloquentCollection<string, User>  $keyedUsers
 * @param  TransactionCollection<int, Transaction>  $transactions
 * @param  Collection<string, int>  $items
 * @param  LazyCollection<string, int>  $lazyItems
 */
function test(
    EloquentCollection $users,
    EloquentCollection $keyedUsers,
    TransactionCollection $transactions,
    Collection $items,
    LazyCollection $lazyItems,
): void {
    assertType('Illuminate\Support\Collection<int, int>', $items->random(2));
    assertType('Illuminate\Support\Collection<string, int>', $items->random(2, true));
    assertType('Illuminate\Support\LazyCollection<string, int>', $lazyItems->random(2, true));

    assertType('Illuminate\Support\Collection<string, int>', $items->random(function ($collection) {
        assertType('Illuminate\Support\Collection<string, int>', $collection);

        return 2;
    }, true));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $users->random(function ($collection) {
        assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection);

        return 2;
    }, true));

    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, int|string|null>>', $items->zip([1], ['one']));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\User|int|null>>', $users->zip([1]));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\Transaction|string|null>>', $transactions->zip(['one']));
    assertType('Illuminate\Support\LazyCollection<int, Illuminate\Support\LazyCollection<int, int|string|null>>', $lazyItems->zip(['one']));

    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, int>>', $items->split(2));
    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->split(2));
    assertType('App\TransactionCollection<int, App\TransactionCollection<int, App\Transaction>>', $transactions->split(2));
    assertType('Illuminate\Support\LazyCollection<int, Illuminate\Support\Collection<int, int>>', $lazyItems->split(2));

    assertType('Illuminate\Support\Collection<int|string, App\User|int>', $items->concat([new User()]));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\Transaction|App\User>', $users->concat([new Transaction()]));
    assertType('App\TransactionCollection<int, App\Transaction|App\User>', $transactions->concat([new User()]));
    assertType('Illuminate\Support\LazyCollection<int, App\User|int>', $lazyItems->concat([new User()]));

    assertType('Illuminate\Support\Collection<int|string, int|string>', $items->pad(10, 'pad'));
    assertType('Illuminate\Support\LazyCollection<int|string, int|string>', $lazyItems->pad(10, 'pad'));
    assertType('Illuminate\Support\Collection<int|string, App\User|string>', $keyedUsers->pad(10, 'pad'));
}
