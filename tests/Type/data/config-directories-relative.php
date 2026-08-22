<?php

namespace ConfigDirectoriesRelative;

use function PHPStan\Testing\assertType;

/**
 * The directory is configured as `config`, relative to the neon file that
 * declares it, so these only resolve if the path was expanded relative to
 * that file rather than to the current working directory.
 */
function test(): void
{
    assertType('string|null', config('package.string'));
    assertType('array{key: string}|null', config('package.nested.deep'));
}
