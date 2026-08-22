<?php

namespace ConfigDirectories;

use Illuminate\Support\Facades\Config;

use function PHPStan\Testing\assertType;

function test(): void
{
    // resolved by statically parsing the configured directories
    assertType('string|null', config('package.string'));
    assertType('int|null', config('package.int'));
    assertType('float|null', config('package.float'));
    assertType('bool|null', config('package.bool'));
    assertType('null', config('package.null'));
    assertType('array{key: string, list: array{int, int, int}, deep: array{key: string}}|null', config('package.nested'));
    assertType('array{key: string}|null', config('package.nested.deep'));
    assertType('string|null', config('package.nested.deep.key'));
    assertType('int|null', config('package.nested.list.0'));
    assertType('string', config('package.string', 'fallback'));
    assertType('mixed', config('package.missing', 'fallback'));
    assertType('string', Config::array('package.nested.deep.key'));
    assertType('array{key: string}', Config::array('package.nested.deep'));
    assertType('string|null', Config::get('package.nested.deep.key'));
    assertType("Illuminate\Support\Collection<'key', string>", Config::collection('package.nested.deep'));
    assertType("array{'package.string': string, 'package.nested.deep.key': string}", Config::getMany(['package.string', 'package.nested.deep.key']));

    // nested directories are scanned too
    assertType('string|null', config('queue.connection'));

    // unknown keys stay mixed
    assertType('mixed', config('package.missing'));
    assertType('mixed', config('missing.key'));

    // the container takes precedence over the parsed files
    assertType('array{guard: string, passwords: string}|null', config('auth.defaults'));

    // declared types are trusted as written
    assertType("'redis'|'sync'|null", config('documented.driver'));
    assertType('int<1, max>|null', config('documented.retries'));
    assertType("array{driver: 'redis'|'sync', retries: int<1, max>}|null", config('documented'));
}
