<?php

declare(strict_types=1);

namespace Tests\Integration\Data;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * A macro's staticness is taken from its closure, because that is how the
 * macro declares which call form it is for. Registering with a static closure
 * says "call this statically"; registering with a plain closure says "call
 * this on an instance", since that is the only form where $this is bound.
 *
 * Calling a macro against that intent is reported, and deliberately so.
 *
 * @param Collection<int, string> $collection
 */
function callForms(Collection $collection): void
{
    Collection::staticClosureMacro();
    $collection->plainClosureMacro();

    // Against the declared intent, so reported.
    Collection::plainClosureMacro();

    // The mirror case. Reported by phpstan-strict-rules as
    // staticMethod.dynamicCall, which is not installed here, so there is no
    // error to assert. Worth reporting: Macroable::__call reaches it through a
    // bindTo fallback while PHP warns that binding an instance to a static
    // closure becomes an error in PHP 9.
    $collection->staticClosureMacro();
}

/**
 * Facades are the exception. A facade proxies to the instance behind it and is
 * always written as a static call, so the closure style cannot express intent
 * there and a static call is never wrong.
 */
function facadeMacros(): void
{
    Route::facadeMacro();
    Route::facadePlainClosureMacro();
}
