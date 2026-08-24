<?php

namespace CollectionStubs;

use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;

use function PHPStan\Testing\assertType;

/**
 * @param EloquentCollection<int, User> $collection
 * @param SupportCollection<string, int> $items
 * @param SupportCollection<int, User> $collectionOfUsers
 */
function test(
    EloquentCollection $collection,
    SupportCollection $items,
    SupportCollection $collectionOfUsers,
    User $user,
): void {
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::all()->each(function (User $user, int $key): void {
    }));

    assertType('Illuminate\Support\Collection<string, int>', $items->each(function (): bool {
        return false;
    }));

    assertType('Illuminate\Support\Collection<string, decimal-int-string>', $items->map(function (int $item): string {
        return (string) $item;
    }));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->find($items));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->find([1]));
    assertType('App\User|null', $collection->find($user));
    assertType('App\User|null', $collection->find(1));
    assertType('App\User|false', $collection->find(1, false));

    // The Collection stub implements ArrayAccess<TKey, mixed> so that TValue can stay
    // covariant, and redeclares offsetGet to keep the element type.
    assertType('App\\User', $collectionOfUsers[0]);
    assertType('int', $items['key']);

    // search() takes its needle on a method-level template for the same reason.
    assertType('int|false', $collectionOfUsers->search($user));
    assertType('string|false', $items->search(1));
    assertType('string|false', $items->search(fn (int $item, string $key): bool => $item > 1));

    assertType('Illuminate\Support\Collection<int, int>', $collection->pluck('id'));
    assertType('Illuminate\Support\Collection<int, non-falsy-string>', User::get()->pluck(fn (User $user) => "$user->id - $user->email", 'id'));
    assertType('Illuminate\Support\Collection<non-falsy-string, int>', User::get()->pluck('id', fn (User $user) => "$user->id - $user->email"));
    assertType('Illuminate\Support\Collection<non-falsy-string, string>', User::get()->pluck(fn (User $user) => $user->email, fn (User $user) => "$user->id - $user->email"));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::all()->mapInto(User::class));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->flatMap(function (User $user, int $id): array {
        return [$user];
    }));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->tap(function ($collection): void {
    }));

    $foo = collect([
        [
            'id'   => 1,
            'type' => 'A',
        ],
        [
            'id'   => 1,
            'type' => 'B',
        ],
    ]);

    $foo
        ->groupBy('type')
        ->map(function ($grouped, $groupKey): array {
            assertType('string', $groupKey);
        });

    assertType('App\User|null', $collection->first());
    assertType('App\User|false', $collection->first(null, false));
    assertType('App\User|null', $collection->first(function ($user) {
        assertType('App\User', $user);

        return $user->id > 1;
    }));
    assertType('App\User|false', $collection->first(function (User $user) {
        assertType('App\User', $user);

        return $user->id > 1;
    }, function () {
        return false;
    }));

    assertType('App\User|null', $collection->firstWhere('blocked'));
    assertType('App\User|null', $collection->firstWhere('blocked', true));
    assertType('App\User|null', $collection->firstWhere('blocked', '=', true));

    assertType('App\User|null', $collection->last());
    assertType('App\User|false', $collection->last(null, false));
    assertType('App\User|null', $collection->last(function (User $user) {
        return $user->id > 1;
    }));
    assertType('App\User|false', $collection->last(function (User $user) {
        return $user->id > 1;
    }, function () {
        return false;
    }));

    assertType('App\User|null', $collection->get(1));
    assertType('App\User', $collection->get(1, new User()));

    assertType('App\User|null', $collection->pull(1));
    assertType('App\User', $collection->pull(1, new User()));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::all()->filter());

    assertType('App\User', $collection->random());
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->random(5));

    assertType('App\User', $collectionOfUsers->random());
    assertType('Illuminate\Support\Collection<int, App\User>', $collectionOfUsers->random(5));

    assertType('App\User|null', $collection->pop());
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->pop(5));

    assertType('App\User|null', $collectionOfUsers->pop());
    assertType('Illuminate\Support\Collection<int, App\User>', $collectionOfUsers->pop(5));

    assertType('App\User|null', $collection->shift());
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection->shift(5));

    // Nested generics are inferred through collect() and map().
    assertType('Illuminate\Support\Collection<int, CollectionStubs\Wrapper<CollectionStubs\Inner<int>>>', collect([new Wrapper(new Inner(42))]));
    assertType('Illuminate\Support\Collection<int, CollectionStubs\Wrapper<CollectionStubs\Inner<int>>>', collect([42])->map(fn (int $i) => new Wrapper(new Inner($i))));

    assertType('App\User|null', $collectionOfUsers->shift());
    assertType('Illuminate\Support\Collection<int, App\User>', $collectionOfUsers->shift(5));
}

/** @template T */
class Wrapper
{
    /** @param  T  $value */
    public function __construct(public mixed $value)
    {
    }
}

/** @template T */
class Inner
{
    /** @param  T  $value */
    public function __construct(public mixed $value)
    {
    }
}
