<?php

declare(strict_types=1);

namespace PaginatorExtension;

use App\User;

use function PHPStan\Testing\assertType;

function test(): void
{
    assertType('Illuminate\Pagination\LengthAwarePaginator<int, App\User>', User::paginate());
    assertType('array<int, App\User>', User::paginate()->all());
    assertType('array<int, App\User>', User::paginate()->items());
    assertType('App\User|null', User::paginate()[0]);

    assertType('Illuminate\Pagination\Paginator<int, App\User>', User::simplePaginate());
    assertType('array<int, App\User>', User::simplePaginate()->all());
    assertType('array<int, App\User>', User::simplePaginate()->items());
    assertType('App\User|null', User::simplePaginate()[0]);

    assertType('Illuminate\Pagination\CursorPaginator<int, App\User>', User::cursorPaginate());
    assertType('array<int, App\User>', User::cursorPaginate()->all());
    assertType('array<int, App\User>', User::cursorPaginate()->items());
    assertType('App\User|null', User::cursorPaginate()[0]);

    assertType('ArrayIterator<int, App\User>', User::query()->paginate()->getIterator());

    // A paginator over models hands back an Eloquent collection.
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::paginate()->getCollection());
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::simplePaginate()->getCollection());
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::cursorPaginate()->getCollection());

    // Anything else stays a support collection.
    assertType('Illuminate\Support\Collection<int, string>', paginatorOfStrings()->getCollection());
    assertType('Illuminate\Support\Collection<int, string>', cursorPaginatorOfStrings()->getCollection());

    // HasMany
    assertType('Illuminate\Pagination\LengthAwarePaginator<int, App\Account>', (new User())->accounts()->paginate());

    // BelongsToMany
    assertType('Illuminate\Pagination\LengthAwarePaginator<int, App\Post&object{pivot: Illuminate\Database\Eloquent\Relations\Pivot}>', (new User())->posts()->paginate());
}

/** @return \Illuminate\Pagination\LengthAwarePaginator<int, string> */
function paginatorOfStrings(): \Illuminate\Pagination\LengthAwarePaginator
{
}

/** @return \Illuminate\Pagination\CursorPaginator<int, string> */
function cursorPaginatorOfStrings(): \Illuminate\Pagination\CursorPaginator
{
}
