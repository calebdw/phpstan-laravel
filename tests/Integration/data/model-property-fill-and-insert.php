<?php

use App\User;

User::query()->fillAndInsert([['email' => 'a@example.com', 'foo' => 'bar']]);
User::query()->fillAndInsertOrIgnore([['email' => 'a@example.com', 'foo' => 'bar']]);
User::query()->fillAndInsertGetId(['email' => 'a@example.com', 'foo' => 'bar']);
User::query()->fillForInsert([['email' => 'a@example.com', 'foo' => 'bar']]);
User::fillAndInsert([['foo' => 'bar']]);

User::query()->fillAndInsert([['email' => 'a@example.com', 'name' => 'A']]);
User::query()->fillAndInsertOrIgnore([]);
User::query()->fillAndInsertGetId(['email' => 'a@example.com']);
User::query()->fillForInsert([4 => ['name' => 'A']]);

function relation(User $user): void
{
    $user->accounts()->fillAndInsert([['name' => 'A', 'foo' => 'bar']]);
    $user->accounts()->fillAndInsert([['name' => 'A']]);
}

/** @param array<int, array{email: string, name: string}> $rows */
function rows(array $rows): void
{
    User::query()->fillAndInsert($rows);
    User::query()->fillForInsert($rows);
}
