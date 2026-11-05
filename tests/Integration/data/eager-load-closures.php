<?php

namespace EagerLoadClosures;

use App\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

function test(string $name): void
{
    User::query()->with(['accounts' => fn ($q) => $q->active()]);
    User::query()->with(['accounts' => fn (HasMany $q) => $q]);

    User::query()->with(['accounts' => fn (BelongsTo $q) => $q]);

    /** @phpstan-ignore-next-line */
    $broken = fn (HasMany $q) => $q->undefined();
    /** @phpstan-ignore-next-line */
    $nested = [User::query()->undefined()];
    User::query()->with(["{$name}.accounts" => $broken]);
    User::query()->with(['group' => $nested, 'accounts' => $broken]);
}

function model(User $user, \App\Post $post): void
{
    $post->load(['user' => fn ($q) => $q->withTrashed()]);
    $post->loadMissing(['user' => fn (BelongsTo $q) => $q->withTrashed()]);
    $user->load(['accounts' => fn ($q) => $q->active()]);

    $user->load(['accounts' => fn (BelongsTo $q) => $q]);
}
