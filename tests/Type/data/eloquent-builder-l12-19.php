<?php

declare(strict_types=1);

namespace EloquentBuilder1219;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

#[UseEloquentBuilder(CustomBuilder::class)]
class A extends Model {}

#[UseEloquentBuilder(CustomBuilder::class)]
class B extends Model
{
    public function newEloquentBuilder($query): AnotherCustomBuilder
    {
        return new AnotherCustomBuilder($query);
    }
}

/** @extends Builder<A> */
class CustomBuilder extends Builder {}

/** @extends Builder<B> */
class AnotherCustomBuilder extends Builder {}

function test(): void
{
    assertType('EloquentBuilder1219\CustomBuilder', A::query());
    assertType('EloquentBuilder1219\AnotherCustomBuilder', B::query());
}
