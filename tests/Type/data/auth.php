<?php

namespace Auth;

use App\CustomGuard;
use App\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;

use function PHPStan\Testing\assertType;

function test(User $user): void
{
    assertType('App\Admin|App\User|null', Auth::user());
    assertType('App\Admin|App\User', Auth::authenticate());
    assertType('bool', Auth::check());
    assertType('int|string|null', Auth::id());
    assertType('null', Auth::guard()->logout());
    assertType('null', Auth::guard()->login($user));

    assertType(SessionGuard::class, Auth::guard());
    assertType(SessionGuard::class, Auth::guard('web'));
    assertType(SessionGuard::class, Auth::guard('admin'));
    assertType(TokenGuard::class, Auth::guard('api'));
    assertType(CustomGuard::class, Auth::guard('custom'));
    assertType(StatefulGuard::class, Auth::guard('unknown'));
    assertType(StatefulGuard::class, Auth::guard($user->name));

    assertType('App\User|null', Auth::guard('web')->user());
    assertType('App\User', Auth::guard('web')->authenticate());
    assertType('App\Admin|null', Auth::guard('admin')->user());
    assertType('App\Admin', Auth::guard('admin')->authenticate());
    assertType('App\User|null', Auth::guard('api')->user());
    assertType('App\User', Auth::guard('api')->authenticate());

    assertType('App\User|null', Auth::guard('custom')->user());
    assertType('App\User', Auth::guard('custom')->authenticate());
    assertType('int', Auth::guard('custom')->customGuardMethod());

    assertType('int|string|null', Auth::guard('web')->id());
    assertType(Authenticatable::class . '|null', Auth::guard('unknown')->user());
}

/** @param 'api'|'custom' $guard */
function testGuardUnion(string $guard): void
{
    assertType('App\CustomGuard|Illuminate\Auth\TokenGuard', Auth::guard($guard));
    assertType('App\User|null', Auth::guard($guard)->user());
}
