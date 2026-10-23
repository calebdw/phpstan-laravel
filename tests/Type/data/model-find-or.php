<?php

namespace ModelFindOr;

use App\Account;
use App\User;

use function PHPStan\Testing\assertType;

function test(): void
{
    assertType('42|App\User', User::query()->findOr(1, fn () => 42));
    assertType('42|App\User', User::query()->findOr(1, callback: fn () => 42));
    assertType('42|App\User', User::query()->findOr(callback: fn () => 42, id: 1));
    assertType('42|App\User', User::findOr(1, fn () => 42));
    assertType("'missing'|App\User", User::query()->findOr(1, ['id', 'name'], fn () => 'missing'));
    assertType('App\AccountCollection<int, App\Account>', Account::query()->findOr([1, 2], fn () => 42));
    assertType('App\AccountCollection<int, App\Account>', Account::findOr([1, 2], fn () => 42));
    assertType('App\User', User::query()->findSole(1, ['id']));
    assertType('App\User', User::query()->findSole([1, 2], ['id']));
    assertType('App\User', User::findSole([1, 2], ['id']));
}

/** @param int|list<int> $id */
function unions(int|array $id, bool $flag): void
{
    assertType('42|App\User|Illuminate\Database\Eloquent\Collection<int, App\User>', User::query()->findOr($id, fn () => 42));
    assertType('42|App\User|Illuminate\Database\Eloquent\Collection<int, App\User>', User::findOr($id, fn () => 42));

    $class = $flag ? User::class : Account::class;
    assertType('42|App\Account|App\User', $class::findOr(1, fn () => 42));
}
