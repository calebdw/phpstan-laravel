<?php

namespace EloquentCollectionMap;

use App\Account;
use App\User;
use App\UserCollection;
use Illuminate\Database\Eloquent\Collection;

use function PHPStan\Testing\assertType;

/** @param Collection<int, User> $users */
function mapping(Collection $users, UserCollection $custom): void
{
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $users->map(fn ($u) => $u));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\Account>', $users->map(fn ($u) => new Account()));
    assertType('Illuminate\Support\Collection<int, App\User|null>', $users->map(fn ($u) => $u->exists ? $u : null));
    assertType('Illuminate\Support\Collection<string, App\User|null>', $users->mapWithKeys(fn ($u) => [$u->name => $u->exists ? $u : null]));
    assertType('Illuminate\Support\Collection<int, string>', $users->map(fn ($u) => 'unknown'));
    assertType('Illuminate\Support\Collection<int, array{id: int, name: string}>', $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]));
    assertType('Illuminate\Support\Collection<int, App\User|null>', $custom->map(fn ($u) => $u->exists ? $u : null));
    assertType('Illuminate\Support\Collection<int, App\User|string>', $users->map(fn ($u) => $u->exists ? $u : $u->name));
    assertType('Illuminate\Support\Collection<int, App\User|null>', $users->map(callback: fn ($u) => $u->exists ? $u : null));
    assertType('Illuminate\Support\Collection<int, App\User>', $users->map(fn ($u) => $u->exists ? $u : null)->filter());
}

/** @param Collection<int, User>|Collection<int, Account> $models */
function unionReceiver(Collection $models): void
{
    assertType('Illuminate\Support\Collection<int, App\Account|App\User|null>', $models->map(fn ($m) => $m->exists ? $m : null));
}

/** @extends Collection<int, User> */
class OverridesMap extends Collection
{
    /**
     * @param callable(User, int): mixed $callback
     * @return $this
     */
    public function map(callable $callback): static
    {
        return $this->each($callback);
    }
}

function overridden(OverridesMap $models): void
{
    assertType('EloquentCollectionMap\OverridesMap', $models->map(fn ($m) => $m->exists ? $m : null));
}
