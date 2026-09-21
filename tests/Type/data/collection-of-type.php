<?php

namespace CollectionOfType;

use App\Account;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/**
 * @param collection-of<User> $users
 * @param collection-of<string, Account> $accounts
 * @param collection-of<User|Account> $union
 */
function test(Collection $users, Collection $accounts, Collection $union): void
{
    assertType('Illuminate\Database\Eloquent\Collection<(int|string), App\User>', $users);
    assertType('App\AccountCollection<string, App\Account>', $accounts);
    assertType('App\AccountCollection<(int|string), App\Account>|Illuminate\Database\Eloquent\Collection<(int|string), App\User>', $union);

    assertType('Illuminate\Database\Eloquent\Collection<(int|string), App\User>', collectionFor(new User()));
    assertType('App\AccountCollection<(int|string), App\Account>', collectionFor(new Account()));
    assertType('App\TransactionCollection<(int|string), App\Transaction>', collectionFor(new \App\Transaction()));
    assertType('App\AccountCollection<int, App\Account>', Account::query()->get());
}

/**
 * @template TModel of Model
 * @param TModel $model
 * @return collection-of<TModel>
 */
function collectionFor(Model $model): Collection
{
    return $model->newCollection();
}

/** @param collection-of<int, Account> $accounts */
function explicitIntegerKeys(Collection $accounts): void
{
    assertType('App\AccountCollection<int, App\Account>', $accounts);
}
