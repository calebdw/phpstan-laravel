<?php

namespace EagerLoadArrays;

use App\Account;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function PHPStan\Testing\assertType;

/** @param Collection<int, User> $users */
function test(Collection $users, string $name): void
{
    User::query()->with(['accounts' => function ($q) {
        assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $q);
    }]);

    User::query()->with([
        'group',
        'accounts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $q),
        'posts.comments:id' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\MorphMany<App\Comment, App\Post>', $q),
        'group' => ['accounts'],
        'missing' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\Relation<Illuminate\Database\Eloquent\Model, App\User>', $q),
    ]);

    User::query()->with(['accounts' => fn (HasMany $q) => assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $q)]);

    User::query()->with([
        'accounts' => fn ($q) => assertType('mixed', $q),
        $name => fn ($q) => assertType('mixed', $q),
    ]);

    User::query()->withOnly(['accounts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $q)]);
    $users->load(['accounts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $q)]);
    $users->loadMissing(['posts.comments' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\MorphMany<App\Comment, App\Post>', $q)]);

    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::query()->with(['accounts' => fn ($q) => $q]));
}

/**
 * @param Builder<User|Account> $builder
 * @param Builder<TModel> $generic
 * @template TModel of Model
 */
function models(Builder $builder, Builder $generic): void
{
    $builder->with(['posts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\Account, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>|Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>', $q)]);

    $generic->with(['posts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\Relation<Illuminate\Database\Eloquent\Model, TModel of Illuminate\Database\Eloquent\Model (function EagerLoadArrays\models(), argument)>', $q)]);
}

/**
 * @template TRelations of array<array-key, array<mixed>|\Closure|string>|string
 * @param eager-load-of<User, TRelations> $relations
 */
function eagerUsers(array|string $relations): void
{
}

function wrapper(): void
{
    eagerUsers(['accounts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $q)]);
}
