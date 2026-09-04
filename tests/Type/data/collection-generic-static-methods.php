<?php

namespace CollectionGenericStaticMethods;

use App\Transaction;
use App\TransactionCollection;
use App\User;
use App\UserCollection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param EloquentCollection<int, User>           $collection
 * @param SupportCollection<string, int>          $items
 * @param TransactionCollection<int, Transaction> $customEloquentCollection
 * @param LazyCollection<int, User>               $lazyCollection
 * @param Enumerable<int, User>                   $enumerableIntUsers
 * @param Enumerable<string, User>                $enumerableStringUsers
 */
function test(
    EloquentCollection $collection,
    SupportCollection $items,
    TransactionCollection $customEloquentCollection,
    UserCollection $secondCustomEloquentCollection,
    LazyCollection $lazyCollection,
    User $user,
    Enumerable $enumerableIntUsers,
    Enumerable $enumerableStringUsers
): void {
    assertType('Illuminate\Database\Eloquent\Collection<int, int>', EloquentCollection::range(1, 10));
    assertType('Illuminate\Support\LazyCollection<int, int>', LazyCollection::range(1, 10));

    assertType('Illuminate\Support\Collection<int, mixed>', $collection->collapse());
    assertType('Illuminate\Support\Collection<int, mixed>', $items->collapse());

    assertType('Illuminate\Database\Eloquent\Collection<int, array<int, App\User|int>>', $collection->crossJoin([1]));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->find($items));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->find([1]));
    assertType('App\User|null', $collection->find($user));
    assertType('App\User|null', $collection->find(1));
    assertType('App\User|false', $collection->find(1, false));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->findOrFail([1]));
    assertType('App\User', $collection->findOrFail(1));

    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->find($items));
    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->find([1]));
    assertType('App\Transaction|null', $customEloquentCollection->find($user));
    assertType('App\Transaction|null', $customEloquentCollection->find(1));
    assertType('App\Transaction|false', $customEloquentCollection->find(1, false));
    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->findOrFail([1]));
    assertType('App\Transaction', $customEloquentCollection->findOrFail(1));

    assertType('App\UserCollection', $secondCustomEloquentCollection->find($items));
    assertType('App\UserCollection', $secondCustomEloquentCollection->find([1]));
    assertType('App\User|null', $secondCustomEloquentCollection->find($user));
    assertType('App\User|null', $secondCustomEloquentCollection->find(1));
    assertType('App\User|false', $secondCustomEloquentCollection->find(1, false));

    assertType('Illuminate\Support\Collection<int, App\User>', $collection->flatten());
    assertType('Illuminate\Support\Collection<int, int>', $items->flatten());

    assertType('Illuminate\Support\Collection<App\User, int>', $collection->flip());
    assertType('Illuminate\Support\Collection<int, string>', $items->flip());

    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $collection->groupBy('id'));
    assertType('Illuminate\Support\Collection<(int|string), Illuminate\Support\Collection<int, int>>', $items->groupBy('id'));

    assertType('Illuminate\Database\Eloquent\Collection<string, App\User>', $collection->keyBy(fn (User $user, int $key): string => $user->email));

    assertType('Illuminate\Support\Collection<int, int>', $collection->keys());
    assertType('Illuminate\Support\Collection<int, string>', $items->keys());

    assertType('Illuminate\Support\Collection<int, string>', $collection->pluck(['email']));
    assertType('Illuminate\Support\Collection<int, mixed>', $items->pluck('foo'));

    assertType('Illuminate\Support\Collection<int, int>', $customEloquentCollection->map(fn (Transaction $transaction): int => $transaction->id));
    assertType('Illuminate\Support\Collection<int, int>', $secondCustomEloquentCollection->map(fn (User $user): int => $user->id));
    assertType('Illuminate\Support\Collection<int, int>', $collection->map(fn (User $user): int => $user->id));
    assertType('Illuminate\Support\Collection<string, int>', $items->map(fn (int $value, string $key): int => $value));

    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->map(fn (Transaction $transaction): Transaction => $transaction));
    assertType('App\UserCollection', $secondCustomEloquentCollection->map(fn (User $user): User => $user));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->map(fn (User $user): User => $user));

    assertType('Illuminate\Database\Eloquent\Collection<int, array<int, int>>', $collection->mapToDictionary(fn (User $u) => [$u->id => $u->id]));
    assertType("App\TransactionCollection<'foo', array<int, int>>", $customEloquentCollection->mapToDictionary(fn (Transaction $t) => ['foo'=> $t->id]));
    assertType('App\UserCollection', $secondCustomEloquentCollection->mapToDictionary(fn (User $t) => ['foo'=> $t->id]));
    assertType("Illuminate\Support\Collection<'foo', array<int, int>>", $items->mapToDictionary(fn (int $v) => ['foo' => $v]));

    assertType('Illuminate\Support\Collection<int, string>', $customEloquentCollection->mapWithKeys(fn (Transaction $transaction): array => [$transaction->id => 'foo']));
    assertType('Illuminate\Support\Collection<int, string>', $secondCustomEloquentCollection->mapWithKeys(fn (User $user): array => [$user->id => 'foo']));
    assertType('Illuminate\Support\Collection<int, int>', $collection->mapWithKeys(fn (User $user): array => [$user->id => $user->id]));
    assertType('Illuminate\Support\Collection<string, int>', $items->mapWithKeys(fn (int $value, string $key): array => ['foo' => $value]));

    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->mapWithKeys(fn (Transaction $transaction): array => [$transaction->id => $transaction]));
    assertType('App\UserCollection', $secondCustomEloquentCollection->mapWithKeys(fn (User $user): array => [$user->email => $user]));
    assertType('Illuminate\Database\Eloquent\Collection<string, App\User>', $collection->mapWithKeys(fn (User $user): array => [$user->name => $user]));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User|string>', $collection->mergeRecursive([2 => 'foo']));
    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->mergeRecursive([1 => new Transaction()]));
    assertType('App\UserCollection', $secondCustomEloquentCollection->mergeRecursive([1 => new User()]));
    assertType('Illuminate\Support\Collection<string, int>', $items->mergeRecursive(['foo' => 2]));

    assertType('Illuminate\Database\Eloquent\Collection<(int|string), int>', $collection->combine([1]));
    assertType('App\TransactionCollection<(int|string), int>', $customEloquentCollection->combine([1]));
    assertType('App\UserCollection', $secondCustomEloquentCollection->combine([1]));
    assertType('Illuminate\Support\Collection<(int|string), string>', $items->combine(['foo']));

    assertType('App\User|null', $collection->pop(1));
    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->pop(2));
    assertType('App\UserCollection', $secondCustomEloquentCollection->pop(2));
    assertType('Illuminate\Support\Collection<int, int>', $items->pop(3));

    assertType('App\Role&object{pivot: Illuminate\Database\Eloquent\Relations\Pivot}', User::firstOrFail(1)->roles->random());
    assertType('App\RoleCollection<int, App\Role&object{pivot: Illuminate\Database\Eloquent\Relations\Pivot}>', User::firstOrFail(1)->roles->random(1));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->random(1));
    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->random(2));
    assertType('App\UserCollection', $secondCustomEloquentCollection->random(2));
    assertType('Illuminate\Support\Collection<int, int>', $items->random(3));

    assertType('App\User|null', $collection->shift(1));
    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->shift(2));
    assertType('App\UserCollection', $secondCustomEloquentCollection->shift(2));
    assertType('Illuminate\Support\Collection<int, int>', $items->shift(3));

    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $collection->sliding(1));
    assertType('App\TransactionCollection<int, App\TransactionCollection<int, App\Transaction>>', $customEloquentCollection->sliding(2));
    assertType('App\UserCollection', $secondCustomEloquentCollection->sliding(2));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<string, int>>', $items->sliding(3));

    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $collection->split(1));
    assertType('App\TransactionCollection<int, App\TransactionCollection<int, App\Transaction>>', $customEloquentCollection->split(2));
    assertType('App\UserCollection', $secondCustomEloquentCollection->split(2));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, int>>', $items->split(3));

    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $collection->splitIn(1));
    assertType('App\TransactionCollection<int, App\TransactionCollection<int, App\Transaction>>', $customEloquentCollection->splitIn(2));
    assertType('App\UserCollection', $secondCustomEloquentCollection->splitIn(2));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<string, int>>', $items->splitIn(3));

    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $collection->chunk(1));
    assertType('App\TransactionCollection<int, App\TransactionCollection<int, App\Transaction>>', $customEloquentCollection->chunk(2));
    assertType('App\UserCollection', $secondCustomEloquentCollection->chunk(2));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<string, int>>', $items->chunk(3));

    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $collection->chunkWhile(fn (User $u) => $u->id > 5));
    assertType('App\TransactionCollection<int, App\TransactionCollection<int, App\Transaction>>', $customEloquentCollection->chunkWhile(fn (Transaction $t) => $t->id > 5));
    assertType('App\UserCollection', $secondCustomEloquentCollection->chunkWhile(fn (User $t) => $t->id > 5));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<string, int>>', $items->chunkWhile(fn ($v) => $v > 5));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->values());
    assertType('App\TransactionCollection<int, App\Transaction>', $customEloquentCollection->values());
    assertType('App\UserCollection', $secondCustomEloquentCollection->values());
    assertType('Illuminate\Support\Collection<int, int>', $items->values());

    assertType('list<App\User>', $collection->values()->all());
    assertType('list<App\Transaction>', $customEloquentCollection->values()->all());
    assertType('list<App\User>', $secondCustomEloquentCollection->values()->all());
    assertType('list<int>', $items->values()->all());
    assertType('list<App\User>', $lazyCollection->values()->all());
    assertType('list<App\User>', $enumerableIntUsers->values()->all());
    assertType('list<App\User>', $enumerableStringUsers->values()->all());
    assertType('array<string, int>', $items->all());

    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\User|int|null>>', $collection->zip([1]));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\Transaction|string|null>>', $customEloquentCollection->zip(['foo']));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\User|string|null>>', $secondCustomEloquentCollection->zip(['foo']));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, int|string|null>>', $items->zip(['foo', 'bar']));

    assertType('Illuminate\Support\Collection<int<0, 1>, Illuminate\Database\Eloquent\Collection<int, App\User>>', $collection->partition('foo'));
    assertType('Illuminate\Support\Collection<int<0, 1>, App\TransactionCollection<int, App\Transaction>>', $customEloquentCollection->partition('foo'));
    assertType('Illuminate\Support\Collection<int<0, 1>, App\UserCollection>', $secondCustomEloquentCollection->partition('foo'));
    assertType('Illuminate\Support\Collection<int<0, 1>, Illuminate\Support\Collection<string, int>>', $items->partition('foo'));

    assertType('Illuminate\Support\Collection<int, App\User|int>', $collection->pad(10, 10));
    assertType('Illuminate\Support\Collection<int, App\Transaction|string>', $customEloquentCollection->pad(10, 'foo'));
    assertType('Illuminate\Support\Collection<int, App\User|string>', $secondCustomEloquentCollection->pad(10, 'foo'));
    assertType('Illuminate\Support\Collection<int|string, int|string>', $items->pad(1, 'bar'));

    assertType('Illuminate\Support\Collection<string, int>', $collection->countBy('email'));
    assertType('Illuminate\Support\Collection<(int|string), int>', $customEloquentCollection->countBy('foo'));
    assertType('Illuminate\Support\Collection<(int|string), int>', $secondCustomEloquentCollection->countBy('foo'));
    assertType('Illuminate\Support\Collection<(int|string), int>', $items->countBy('bar'));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->concat([new User()]));
    assertType('App\TransactionCollection<int, App\Transaction|App\User>', $customEloquentCollection->concat([new User()]));
    assertType('App\UserCollection', $secondCustomEloquentCollection->concat([new User()]));
    assertType('Illuminate\Support\Collection<int|string, App\User|int>', $items->concat([new User()]));

    ////////////////////////////
    // EnumeratesValues Trait //
    ////////////////////////////

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', EloquentCollection::make([new User()]));
    assertType('App\TransactionCollection<int, App\Transaction>', TransactionCollection::make([new Transaction()]));
    assertType('Illuminate\Support\Collection<int, int>', SupportCollection::make([1, 2, 3]));

    assertType('Illuminate\Database\Eloquent\Collection<(int|string), App\User>', EloquentCollection::wrap([new User()]));
    assertType('App\TransactionCollection<(int|string), App\Transaction>', TransactionCollection::wrap([new Transaction()]));
    assertType('Illuminate\Support\Collection<(int|string), int>', SupportCollection::wrap([1, 2, 3]));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', EloquentCollection::times(10, fn ($int) => new User()));
    assertType('App\TransactionCollection<int, App\Transaction>', TransactionCollection::times(10, fn ($int) => new Transaction()));
    assertType('Illuminate\Support\Collection<int, int>', SupportCollection::times(10, fn ($int) => 5));
    assertType('Illuminate\Support\LazyCollection<int, int>', LazyCollection::times(10, fn ($int) => 5));

    assertType(
        'Illuminate\Database\Eloquent\Collection<int, int>',
        $collection->flatMap(function (User $user, int $id) {
            return [$user->id];
        })
    );

    assertType(
        'Illuminate\Support\Collection<int, int>',
        $items->flatMap(function (int $int) {
            return [$int * 2];
        })
    );

    assertType(
        'Illuminate\Support\LazyCollection<int, int>',
        $lazyCollection->flatMap(function (User $user, int $id) {
            return [$user->id];
        })
    );

    assertType(
        'Illuminate\Support\LazyCollection<int, int>',
        LazyCollection::times(10, fn ($int) => 5)->flatMap(fn (int $i) => [$i * 2]),
    );

    assertType('Illuminate\Support\Collection<string, Illuminate\Support\Collection<int, array{id: int, type: string}>>', collect([
        [
            'id'   => 1,
            'type' => 'A',
        ],
        [
            'id'   => 1,
            'type' => 'B',
        ],
    ])->groupBy('type'));

    assertType('int|false', $enumerableIntUsers->search(fn (User $user) => $user->id === 1));
    assertType('string|false', $enumerableStringUsers->search(fn (User $user) => $user->id === 1));
}
