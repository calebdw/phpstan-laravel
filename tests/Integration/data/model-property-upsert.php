<?php

use App\User;
use Illuminate\Support\Facades\DB;

User::query()->upsert([['foo' => 1]], 'id');
User::query()->upsert([['id' => 1]], 'foo');
User::query()->upsert([['id' => 1]], 'id', ['foo']);
User::query()->upsert([['id' => 1]], 'id', ['foo' => DB::raw('1')]);
(new User())->accounts()->upsert([['id' => 1]], 'foo');
User::query()->upsert([['id' => 1, 'name' => 'updated']], ['id'], ['name']);
User::query()->upsert(['id' => 1, 'name' => 'updated'], 'id', ['name' => DB::raw("'updated'")]);
User::query()->upsert([['id' => 1, 'name' => 'updated']], 'id', ['id', 'name' => DB::raw("'updated'")]);
