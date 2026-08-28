# Configuration rules

Reading configuration in a way that fails at runtime, or that silently
returns `null` once the config is cached.

## Config accessor

`laravel.configAccessor` &middot; option `rules.configAccessor` &middot; on by default

Checks the config repository's typed accessors---`string`, `integer`, `float`,
`boolean`, `array` and `collection`---against the type of the key being read.
None of them coerce: each throws an `InvalidArgumentException` when the value
is not already of the required type, and `collection()` delegates to `array()`.
Reading a key of the wrong type is therefore a guaranteed runtime failure
rather than a style issue.

### Examples

```php
// config/auth.php
return [
    'defaults' => [
        'guard' => 'web',
    ],
    'password_timeout' => 10800,
];
```

```php
Config::string('auth.defaults.guard'); // fine, the value is a string
Config::array('auth.defaults');        // fine, the value is an array
Config::array('auth.defaults.guard');  // always throws
```

The last call reports:

```
Config key 'auth.defaults.guard' is string, but 'array' requires an array.
```

Because the checks are strict, an integer is not accepted where a float is
required:

```php
Config::float('auth.password_timeout'); // always throws, the value is an int
```

Both call styles are covered, so `Config::string(...)`, `config()->string(...)`
and a repository injected as either the concrete class or the contract are all
checked.

A key is only reported when its type is genuinely known: resolved from the
booted container, or from
[`configDirectories`](../reference/configuration.md#configdirectories) for keys
the container cannot answer. Anything neither can resolve could hold any type,
so it is left alone; an unrecognised key cannot produce a false positive.

Passing a default does not suppress the error, because a default only applies
when the key is *absent*. A key that exists holding the wrong type is returned
and rejected either way:

```php
Config::string('auth.password_timeout', 'fallback'); // still throws
```

One case is deliberately not reported: a key explicitly set to `null` is
indistinguishable from a missing key once resolved, so it is skipped even
though it would throw.

Values are read from the environment the analysis runs in. A key whose value
comes from `env()` is judged by the local environment, so a value whose type
differs between environments is checked against the local one.

### Configuration

```neon
parameters:
    laravel:
        rules:
            configAccessor: false
```

## Env call outside config

`laravel.envCallOutsideConfig` &middot; option `rules.envCallOutsideConfig` &middot; on by default

Checks for `env()` calls outside the `config` directory, which return `null`
once the config is cached.

### Examples

Suppose this calls happens somewhere in your code outside the `config` directory:

```php
env('APP_ENV')
```

It will result in the following error:

```
Called 'env' outside of the config directory which returns null when the config is cached, use 'config'.")
```

Use the corresponding configuration option instead:

```php
config('app.env')
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            envCallOutsideConfig: false
```

The application's own config directory is where `env()` is allowed by default.
If your configuration files live elsewhere, name those directories with
[`configDirectories`](../reference/configuration.md#configdirectories):

```neon
parameters:
    laravel:
        configDirectories:
            - src/config
            - tests
```

## Undefined config name

`laravel.undefinedConfigName` &middot; option `rules.undefinedConfigName` &middot; off by default

Checks the name handed to a manager against the configuration that defines the
names it accepts. Every one of these lookups throws an
`InvalidArgumentException` when the name is not configured, so a typo is a
guaranteed runtime failure rather than a style issue:

| Call | Names come from |
| --- | --- |
| `Storage::disk()`, `::drive()` | `filesystems.disks` |
| `Cache::store()`, `::driver()` | `cache.stores` |
| `DB::connection()` | `database.connections` |
| `Queue::connection()` | `queue.connections` |
| `Mail::mailer()` | `mail.mailers` |
| `Log::channel()`, `::driver()` | `logging.channels` |
| `Broadcast::connection()` | `broadcasting.connections` |
| `Auth::guard()` | `auth.guards` |

### Examples

```php
Storage::disk('s3');       // fine, filesystems.disks defines it
Storage::disk('s3-backup'); // always throws
```

The last call reports the message the call would itself throw:

```
Disk [s3-backup] does not have a configured driver.
```

Both call styles are covered, so `Cache::store(...)`, `$manager->store(...)`
and a manager injected as either the concrete class or its contract are all
checked.

A database connection may name one side of a read/write pair, which is not
part of the name configuration defines, so the suffix is stripped before the
lookup:

```php
DB::connection('mysql::read');  // fine, mysql is configured
DB::connection('mysql::stale'); // always throws, not a side
```

Only a name known statically is checked. A variable, or configuration that
neither the booted container nor
[`configDirectories`](../reference/configuration.md#configdirectories) can
resolve, could be anything and is left alone. Calling with no argument or with
`null` asks for the default, which configuration always names.

### Why it is off by default

A name does not have to come from configuration. `Storage::fake('avatars')`
and `Storage::set()` register a disk directly, which is how Laravel's own
testing documentation writes it:

```php
Storage::fake('avatars');
Storage::disk('avatars'); // works at runtime, reported by this rule
```

Nothing in the analysis can see that registration, so the rule would report a
disk that does exist. Turn it on if you resolve every name from configuration,
or ignore [`laravel.undefinedConfigName`](../reference/identifiers.md) in your
test suite:

```neon
parameters:
    laravel:
        rules:
            undefinedConfigName: true
```
