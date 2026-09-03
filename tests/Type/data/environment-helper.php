<?php

namespace EnvironmentHelper;

use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Application;

use function PHPStan\Testing\assertType;

function test(): void
{
    assertType('string', app()->environment());
    assertType('bool', app()->environment('local'));
    assertType('bool', app()->environment(['local', 'production']));
}

// The concrete application implements the contract, so registering the
// extension for the contract alone covers both.
function testInstances(Application $app, ApplicationContract $contract): void
{
    assertType('string', $app->environment());
    assertType('bool', $app->environment('local'));

    assertType('string', $contract->environment());
    assertType('bool', $contract->environment('local'));
}
