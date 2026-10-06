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

    // a nested file is keyed by its path, the way Laravel loads it, so
    // config/module/queue.php declares `module.queue` and not `queue`
    assertType('array{connection: string}|null', config('module.queue'));
    assertType('string|null', config('module.queue.connection'));
    assertType('mixed', config('queue.connection'));

    // a directory on its own declares nothing
    assertType('mixed', config('module'));

    // config/module/package.php and config/package.php share a name but not
    // a key, so neither shadows the other
    assertType('int|null', config('module.package.from'));
    assertType('string|null', config('package.string'));

    // nesting goes as deep as the directories do
    assertType('string|null', config('email.engineering.designs.subject'));

    // config/override/nested.php is loaded after config/override.php, so the
    // nested file is what `override.nested` holds
    assertType('string|null', config('override.nested.from'));
    assertType('string|null', config('override.own'));

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
