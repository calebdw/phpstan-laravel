<?php

namespace ConfigAccessorRule;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use Illuminate\Support\Facades\Config;

// Values resolved from the booted container.
function testContainerValues(): void
{
    Config::array('auth.defaults');
    Config::collection('auth.guards');
    Config::string('auth.defaults.guard');
    Config::integer('auth.password_timeout');
    Config::boolean('auth.guards.api.hash');

    Config::array('auth.defaults.guard');
    Config::collection('auth.defaults.guard');
    Config::string('auth.password_timeout');
    Config::integer('auth.defaults.guard');
    Config::boolean('auth.password_timeout');

    // None of the accessors coerce, so an int is not a float.
    Config::float('auth.password_timeout');
}

// Values resolved by parsing configDirectories.
function testParsedValues(): void
{
    Config::string('values.string');
    Config::integer('values.integer');
    Config::float('values.float');
    Config::boolean('values.boolean');
    Config::array('values.array');

    Config::integer('values.float');
    Config::string('values.boolean');
    Config::boolean('values.array');
}

function testUnknownKeys(): void
{
    // Unknown keys could hold anything.
    Config::string('values.missing');
    Config::integer('nonexistent.key');

    // A null value is indistinguishable from a missing key.
    Config::array('auth.null');
    Config::string('auth.null');
}

function testDefaultDoesNotSuppress(): void
{
    // A default applies only when the key is absent, so an existing
    // key holding the wrong type is still returned and rejected.
    Config::string('auth.password_timeout', 'fallback');
}

function testRepository(Repository $config): void
{
    $config->integer('auth.password_timeout');
    $config->integer('auth.defaults.guard');
}

function testContract(RepositoryContract $config): void
{
    $config->float('auth.password_timeout');
}

function testHelper(): void
{
    config()->boolean('auth.guards.api.hash');
    config()->boolean('auth.defaults.guard');
}

function testNonConstantKey(string $key): void
{
    Config::string($key);
}
