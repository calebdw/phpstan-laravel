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

    // A key PHPStan cannot read collapses the array, taking the literal keys
    // beside it with it. The relation is still one of the model's, which is
    // worth more to the closure than a mixed.
    User::query()->with([
        'accounts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\Relation<Illuminate\Database\Eloquent\Model, App\User>', $q),
        $name => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\Relation<Illuminate\Database\Eloquent\Model, App\User>', $q),
    ]);

    User::query()->withOnly(['accounts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $q)]);
    $users->load(['accounts' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $q)]);
    $users->loadMissing(['posts.comments' => fn ($q) => assertType('Illuminate\Database\Eloquent\Relations\MorphMany<App\Comment, App\Post>', $q)]);

    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::query()->with(['accounts' => fn ($q) => $q]));
}

/**
 * @param Builder<User|Account> $builder
 * @param Builder<TModel> $generic
 * @param Builder<Model> $any
 * @template TModel of Model
 */
function models(Builder $builder, Builder $generic, Builder $any, string $name): void
{
    // A query that never said which model it runs on has every relation in
    // Laravel as a candidate, so naming them says nothing and would reject
    // what a real relation accepts - a literal key names one no better.
    $any->with([$name => fn ($q) => assertType('mixed', $q)]);
    $any->with(['payment' => fn ($q) => assertType('mixed', $q)]);

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
