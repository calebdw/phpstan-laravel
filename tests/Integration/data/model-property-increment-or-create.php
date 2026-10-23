<?php

use App\User;

User::query()->incrementOrCreate(['foo' => 1], 'id');
User::query()->incrementOrCreate(['id' => 1], 'foo');
User::query()->incrementOrCreate(['id' => 1], 'id', extra: ['foo' => 1]);
User::query()->incrementOrCreate(['id' => 1], 'id', extra: ['name' => 'updated']);
