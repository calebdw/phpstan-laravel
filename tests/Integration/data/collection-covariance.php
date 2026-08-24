<?php

namespace CollectionCovariance;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/** @param Collection<int, string|null> $values */
function acceptNullableStrings(Collection $values): void
{
}

/** @param LazyCollection<int, string|null> $values */
function acceptLazyNullableStrings(LazyCollection $values): void
{
}

/** @param Collection<int, User|null> $users */
function acceptNullableUsers(Collection $users): void
{
}

function test(): void
{
    $value = random_int(0, 1) === 1 ? 'a' : null;

    acceptNullableStrings(collect([$value]));
    acceptNullableStrings(new Collection([$value]));
    acceptLazyNullableStrings(LazyCollection::make([$value]));
    acceptNullableUsers(User::all()->toBase());
}

/** @return Collection<int, string|null> */
function nullableColumn(): Collection
{
    return User::query()->pluck('name');
}
