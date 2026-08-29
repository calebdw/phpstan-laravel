<?php

namespace Arrayable;

use App\User;
use Illuminate\Contracts\Support\Arrayable;

use function PHPStan\Testing\assertType;

/** @param  Arrayable<string, int> $arrayable */
function test(Foo $foo, Bar $bar, User $user, Arrayable $arrayable): void
{
    assertType('array<string, int>', $foo->toArray());
    assertType('array<string, float>', $bar->toArray());
    assertType('array<string, int>', $arrayable->toArray());
    assertType('array<string, mixed>', $user->toArray());
}

/** @implements Arrayable<string, int> */
class Foo implements Arrayable
{
    public function toArray(): array
    {
        return [];
    }
}

/** @implements Arrayable<string, float> */
abstract class Bar implements Arrayable
{
}
