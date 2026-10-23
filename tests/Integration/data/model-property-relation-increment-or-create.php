<?php

use App\User;

(new User())->accounts()->incrementOrCreate(['id' => 1], 'foo');
(new User())->roles()->incrementOrCreate(['foo' => 1], 'id');
