<?php

namespace CollectionNullsafeChain;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

class Holder
{
    /** @param Collection<int, User|null> $users */
    public function __construct(public Collection $users)
    {
    }
}

/** @param LazyCollection<int, User|null>|null $lazy */
function test(?Holder $holder, ?LazyCollection $lazy): void
{
    assertType('Illuminate\Support\Collection<int, App\User>|null', $holder?->users->filter(fn ($u) => $u instanceof User));
    assertType('Illuminate\Support\Collection<int, App\User>|null', $holder?->users->filter());
    assertType('Illuminate\Support\Collection<int, App\User>|null', $holder?->users->reject(fn ($u) => $u === null));
    assertType('Illuminate\Support\Collection<int, App\User>|null', $holder?->users->whereNotNull());
    assertType('Illuminate\Support\Collection<int, null>|null', $holder?->users->whereNull());
    assertType('Illuminate\Support\Collection<int, App\User>|null', $holder?->users->values()->filter(fn ($u) => $u instanceof User));
    assertType('Illuminate\Support\LazyCollection<int, App\User>|null', $lazy?->filter());
    assertType('Illuminate\Support\LazyCollection<int, App\User>|null', $lazy?->whereNotNull());
}
