<?php

namespace CollectionWrites;

use Illuminate\Support\Collection;

/**
 * TValue is covariant, so these writes are unsound in principle. They are still
 * checked, because the framework declares them and every real codebase relies on
 * the check. This file exists to catch a stub change that drops it.
 *
 * @param Collection<int, string> $strings
 */
function test(Collection $strings): void
{
    $strings->push(1);
    $strings->put(1, 2);
    $strings->prepend(3);
    $strings[0] = 4;
}
