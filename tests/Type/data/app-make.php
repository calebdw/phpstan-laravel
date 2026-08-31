<?php

namespace AppMake;

use Illuminate\Support\Facades\App;
use CalebDW\PhpstanLaravel\Support\ApplicationResolver;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/** @param class-string<Model> $model */
function test(string $model, mixed $mixed): void
{
    assertType(ApplicationResolver::class, App::make(ApplicationResolver::class));
    assertType(ApplicationResolver::class, App::makeWith(ApplicationResolver::class));
    assertType('mixed', App::make($model));
    assertType('mixed', App::makeWith($model));
    assertType('mixed', App::make($mixed));
    assertType('mixed', App::makeWith($mixed));
}
