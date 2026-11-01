<?php

namespace PaginationSignatures;

use App\User;
use Illuminate\Pagination\Cursor;

use function PHPStan\Testing\assertType;

function paginate(User $user, Cursor $cursor): void
{
    assertType('Illuminate\Pagination\LengthAwarePaginator<int, App\User>', User::query()->paginate(function ($total) {
        assertType('int', $total);

        return $total > 100 ? 30 : 15;
    }));
    assertType('Illuminate\Pagination\LengthAwarePaginator<int, App\User>', User::paginate(perPage: fn ($t) => $t > 100 ? 30 : null, total: 120));
    assertType('Illuminate\Pagination\LengthAwarePaginator<int, App\Account>', $user->accounts()->paginate(fn ($t) => 15));
    assertType('Illuminate\Contracts\Pagination\CursorPaginator<int, App\Role&object{pivot: Illuminate\Database\Eloquent\Relations\Pivot}>', $user->roles()->cursorPaginate(cursor: $cursor));
    assertType('Illuminate\Pagination\CursorPaginator<int, App\Transaction>', $user->transactions()->cursorPaginate(15, ['*'], 'cursor', $cursor));
    assertType('Illuminate\Pagination\CursorPaginator<int, App\Car>', $user->car()->cursorPaginate(cursor: $cursor));
    assertType('Illuminate\Contracts\Pagination\CursorPaginator<int, App\Role&object{pivot: Illuminate\Database\Eloquent\Relations\Pivot}>', $user->roles()->cursorPaginate(cursor: 'encoded'));
    assertType('Illuminate\Pagination\CursorPaginator<int, App\Transaction>', $user->transactions()->cursorPaginate(cursor: null));
}
