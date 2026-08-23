# Configuration types

Configuration is one of the few places where Laravel hands you a value whose
type is knowable but unstated. `config('app.timezone')` is a `string` in every
run of your application, and yet the signature can only promise `mixed`.

This extension answers those calls from the config repository in the booted
container. Because booting registers every service provider, that repository
holds the merged configuration of your application and all of its packages, so
in an application there is nothing to configure:

```php
config('auth.defaults');       // array{guard: string, passwords: string}|null
config('auth.defaults.guard'); // string|null
config('nope.missing');        // mixed
```

The facade, the helper and an injected repository are all covered, as are the
contract and the concrete class:

```php
use Illuminate\Contracts\Config\Repository;

Config::get('auth.defaults');                  // array{...}|null
config()->get('auth.defaults');                // array{...}|null
app(Repository::class)->get('auth.defaults');  // array{...}|null
```

Passing a default replaces the `null`, since a default is what you get when the
key is absent:

```php
config('auth.defaults', 'bar'); // 'bar'|array{guard: string, passwords: string}
```

## Why the results are nullable

`config()` returns `null` for a key that does not exist, so a resolved key is
the value type unioned with `null` rather than the value type alone. That is
accurate rather than pedantic: nothing guarantees a deployed config file still
has the key your local one does.

The typed accessors are not nullable, because they throw instead of returning
`null`:

```php
config('auth.defaults');       // array{guard: string, passwords: string}|null
Config::array('auth.defaults'); // array{guard: string, passwords: string}
```

That makes `Config::array()`, `Config::string()` and friends the better choice
for a key you require, and it is worth reaching for them where you would
otherwise have written `?? throw` or a null check.

## Scalars widen, shapes do not

A scalar is widened to its general type: `'UTC'` becomes `string`, not `'UTC'`.
The value in the file is only the default, and the deployed value can be
anything of that type, so treating it as a literal would let code depend on a
value that changes between environments.

Array shapes are kept, because the *set of keys* is a property of the file
rather than of the environment, and nesting is followed all the way down:

```php
config('auth.defaults');       // array{guard: string, passwords: string}|null
config('auth.defaults.guard'); // string|null
```

## Typed accessors are checked

Laravel's typed accessors do not coerce. Each throws an
`InvalidArgumentException` when the value is not already of the required type,
which makes reading a key of the wrong type a guaranteed runtime failure rather
than a style issue. Since the types are known, that can be caught:

```php
Config::string('auth.defaults.guard'); // fine, the value is a string
Config::array('auth.defaults');        // fine, the value is an array
Config::array('auth.defaults.guard');  // always throws
```

```
Config key 'auth.defaults.guard' is string, but 'array' requires an array.
```

The checks are strict in the same way Laravel is, so an `int` is not accepted
where a `float` is required. See [the config accessor
rule](../rules/config.md#config-accessor) for the details, including why
passing a default does not suppress the error.

## Packages, and config the container cannot see

The container has no answer for a package's own config files, since nothing
publishes or merges them when there is no application. Setting
[`configDirectories`](../reference/configuration.md#configdirectories) parses
those files statically instead:

```neon
parameters:
    laravel:
        configDirectories:
            - config
            - modules/*/config
```

The container is always asked first, so this can only fill in keys the
container does not already have. It can never change the types an application
gets for its own configuration.

When the inferred shape is too lossy, annotate the returned array and the
declared type is used verbatim:

```php
// config/pennant.php

/** @return array{default: 'array'|'database', stores: array<string, array{connection: string|null}>} */
return [
    'default' => 'database',
    'stores' => [
        'database' => ['connection' => null],
    ],
];
```

```php
config('pennant.default'); // 'array'|'database'|null
```

## Where env() fits

`env()` returns `bool|string|null`, unioned with whatever default you pass,
because that is what reading an environment variable actually gives you. That
is deliberately unhelpful, and the reason is
[`envCallOutsideConfig`](../rules/config.md#env-call-outside-config): outside
the config directory `env()` returns `null` as soon as the config is cached, so
the fix is not a better type but calling `config()` instead.

## Limits

`config()->all()` and `Config::all()` are answered from the container only.
Returning them from parsed files would mean reading every config file on every
run, which defeats the laziness that makes this free when you are not using it.

A key whose value is built at runtime, from a function call or a match on the
environment, is typed as whatever PHPStan infers for that expression, which may
be `mixed`.

Values are read from the environment the analysis runs in, so a key whose type
differs between environments is judged by your local one.
