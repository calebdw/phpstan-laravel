<?php

declare(strict_types=1);

namespace CacheRemember;

use App\User;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

use function PHPStan\Testing\assertType;

function testRememberWithWarmth(int $id, Repository $concrete): void
{
    assertType('array{App\User, bool}', Cache::rememberWithWarmth(
        "user:$id",
        3600,
        fn () => User::findOrFail($id),
    ));

    assertType('array{App\User, bool}', $concrete->rememberWithWarmth(
        "user:$id",
        3600,
        fn () => User::findOrFail($id),
    ));
}
