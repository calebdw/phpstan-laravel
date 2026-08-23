<?php

declare(strict_types=1);

namespace EloquentBuilderPluck;

use App\Account;
use App\Post;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function PHPStan\Testing\assertType;

/**
 * @param Builder<User> $users
 */
function test(Builder $users): void {
    assertType('Illuminate\Support\Collection<int, string>', $users->pluck('name'));
    assertType('Illuminate\Support\Collection<string, string>', $users->pluck('name', 'name'));
}

/**
 * A relation forwards pluck() to its underlying builder, so the column
 * resolves against the related model exactly as it does on the builder.
 */
function testRelations(User $user, Post $post): void {
    assertType('Illuminate\Support\Collection<int, string>', $user->accounts()->pluck('name'));
    assertType('Illuminate\Support\Collection<string, string>', $user->accounts()->pluck('name', 'name'));
    assertType('Illuminate\Support\Collection<int, int>', $user->accounts()->pluck('id'));
    assertType('Illuminate\Support\Collection<int, string>', $user->accounts()->pluck('name', 'id'));

    // The related model, not the declaring one.
    assertType('Illuminate\Support\Collection<int, string>', $post->user()->pluck('name'));

    // Chaining through builder methods keeps the relation as the receiver.
    assertType('Illuminate\Support\Collection<int, string>', $user->accounts()->where('active', true)->pluck('name'));
}

/**
 * @param HasMany<Account, User> $accounts
 * @param BelongsTo<User, Post>  $author
 */
function testRelationParameters(HasMany $accounts, BelongsTo $author): void {
    assertType('Illuminate\Support\Collection<int, string>', $accounts->pluck('name'));
    assertType('Illuminate\Support\Collection<int, string>', $author->pluck('name'));
}
