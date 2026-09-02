<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\HandleExceptions;

require_once __DIR__.'/vendor/autoload.php';

$filesystem = new Filesystem();

$filesystem->copyDirectory(__DIR__.'/tests/application/database/migrations', __DIR__.'/vendor/orchestra/testbench-core/laravel/database/migrations');
$filesystem->copyDirectory(__DIR__.'/tests/application/database/schema', __DIR__.'/vendor/orchestra/testbench-core/laravel/database/schema');
$filesystem->copyDirectory(__DIR__.'/tests/application/config', __DIR__.'/vendor/orchestra/testbench-core/laravel/config');
$filesystem->copyDirectory(__DIR__.'/tests/application/resources', __DIR__.'/vendor/orchestra/testbench-core/laravel/resources');
$filesystem->copyDirectory(__DIR__.'/tests/application/app/Console', __DIR__.'/vendor/orchestra/testbench-core/laravel/app/Console');

// PHPStan runs the `bootstrapFiles` from tests/phpstan-tests.neon with `require_once`
// when it first builds a container, which lands inside whichever test happens to get
// there first. Booting the kernel installs error and exception handlers that are never
// removed, and PHPUnit reports the resulting handler-stack delta as a risky test.
// Loading the same files here runs them once, before any test, so `require_once` makes
// PHPStan skip them and no test ever sees the handlers appear or disappear.
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/tests/bootstrap.php';

HandleExceptions::flushHandlersState();
