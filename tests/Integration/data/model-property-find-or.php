<?php

use App\User;
use Illuminate\Support\Facades\DB;

User::query()->findSole(1, ['foo']);
User::query()->findOr(1, ['foo'], fn () => null);
User::findOr(1, ['foo'], fn () => null);
User::query()->findSole(1, ['id', DB::raw('1')]);
User::query()->findOr(1, ['id'], fn () => null);
