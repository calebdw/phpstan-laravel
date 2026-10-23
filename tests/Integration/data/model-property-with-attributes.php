<?php

use App\User;

User::query()->withAttributes(['foo' => 1]);
User::query()->withAttributes('foo', 1);
User::query()->withAttributes(['name' => 'updated'], asConditions: false);
User::query()->withAttributes('name', 'updated');
