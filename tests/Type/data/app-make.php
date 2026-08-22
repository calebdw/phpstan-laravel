<?php

namespace AppMake;

use Illuminate\Support\Facades\App;
use CalebDW\PhpstanLaravel\ApplicationResolver;

use function PHPStan\Testing\assertType;

function test(): void
{
    assertType(ApplicationResolver::class, App::make(ApplicationResolver::class));
    assertType(ApplicationResolver::class, App::makeWith(ApplicationResolver::class));
}
