<?php

namespace WhereIntegerInRaw;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** @param Collection<int, int> $ids */
function test(Collection $ids): void
{
    User::query()->whereIntegerInRaw('id', $ids);
    User::query()->orWhereIntegerInRaw('id', $ids);
    User::query()->whereIntegerNotInRaw('id', $ids);
    User::query()->orWhereIntegerNotInRaw('id', $ids);

    DB::table('users')->whereIntegerInRaw('id', $ids);
    DB::table('users')->orWhereIntegerInRaw('id', $ids);
    DB::table('users')->whereIntegerNotInRaw('id', $ids);
    DB::table('users')->orWhereIntegerNotInRaw('id', $ids);

    User::query()->whereIntegerInRaw('id', User::query()->pluck('id'));

    User::query()->whereIntegerNotInRaw('id', [1, 2]);
    User::query()->orWhereIntegerNotInRaw('id', [1, 2]);
    DB::table('users')->whereIntegerNotInRaw('id', [1, 2]);
    DB::table('users')->orWhereIntegerNotInRaw('id', [1, 2]);
}
