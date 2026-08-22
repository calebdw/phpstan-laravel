# Custom config parameters

All custom config parameters that are defined by this extension are listed here.

## `noUnnecessaryCollectionCall`, `noUnnecessaryCollectionCallOnly`, `noUnnecessaryCollectionCallExcept`

These parameters are related to the `NoUnnecessaryCollectionCall` rule. You can find the details about these parameters and the rule [here](rules.md#NoUnnecessaryCollectionCall).

## `databaseMigrationsPath`

By default, the default Laravel database migration path (`database/migrations`) is used to scan migration files to understand the table structure and model properties. If you have database migrations in other place than the default, you can use this config parameter to tell this extension where the database migrations are stored.

You can give absolute paths, or paths relative to the PHPStan config file.
Paths with wildcards are also supported (passed to `glob` function).

### Example

```neon
parameters:
    laravel:
        databaseMigrationsPath:
            - app/Domain/*/migrations
```

**Note:** If your migrations are using `if` statements to conditionally alter database structure (ex: create table only if it's not there, add column only if table exists and column does not etc...) this extension will assume those if statements evaluate to true and will consider everything from the `if` body.

## `disableMigrationScan`

**default**: `false`

You can disable use this config to disable migration scanning.

### Example

```neon
parameters:
    laravel:
        disableMigrationScan: true
```

## `squashedMigrationsPath`

By default, this extension will check `database/schema` directory to find schema dumps. If you have them in other locations or if you have multiple folders, you can use this config option to add them.

Paths with wildcards are also supported (passed to `glob` function).

### Example

```neon
parameters:
    laravel:
        squashedMigrationsPath:
            - app/Domain/*/schema
```

### PostgreSQL

Both supported parsers are primarily focused on the MySQL dialect.
It can read (or rather, try to read) PostgreSQL dumps provided they are in the *plain text (and not the 'custom') format*, but the mileage may vary as problems have been noted with timestamp columns and lengthy parse time on more complicated dumps.

The viable options for PostgreSQL at the moment are:

1. Use the [laravel-ide-helper](https://github.com/barryvdh/laravel-ide-helper) package to write PHPDocs directly to the Models.
2. Use the [laravel-migrations-generator](https://github.com/kitloong/laravel-migrations-generator) to generate migration files (or a singular squashed migration file) for this extension to scan with the `databaseMigrationsPath` setting.

## `sqlParser`

**default**: `auto`

Selects which SQL parser reads your squashed schema dumps. Neither parser is a
hard requirement of this package, so you choose which one — and which license —
enters your dependency tree:

```bash
composer require --dev iamcal/sql-parser      # MIT
composer require --dev phpmyadmin/sql-parser  # GPL-2.0-or-later
```

| Driver | Uses | Notes |
| --- | --- | --- |
| `auto` | whichever is installed | Prefers `phpmyadmin` when both are present |
| `iamcal` | `iamcal/sql-parser` | MIT, no dependencies of its own |
| `phpmyadmin` | `phpmyadmin/sql-parser` | GPL-2.0-or-later, understands more of the MySQL dialect |

### Example

```neon
parameters:
    laravel:
        sqlParser: iamcal
```

`auto` picks whatever is available, and fails with installation instructions if
neither is. Naming a driver explicitly is a stronger statement: if that parser
is not installed the analysis fails rather than quietly falling back to the
other one, so a project that has deliberately chosen a parser cannot silently
end up using a different one.

If you do not use squashed schema dumps at all, you need neither package — the
parser is only resolved when there is a dump to read. You can also set
[`disableSchemaScan`](#disableschemascan) to skip them entirely.

### A note on the GPL-2.0 parser

`phpmyadmin/sql-parser` is GPL-2.0-or-later, which puts people off more than it
should. It is worth being precise about what that license actually requires.

The GPL's copyleft obligations are triggered by **distributing** the licensed
code, or a derivative work of it. A development-only static analysis dependency
is neither. It is not linked into your application, it is not shipped with it,
and `composer install --no-dev` — what you run to build a production install —
leaves it out of the dependency tree entirely. Nothing you deploy contains any
of it.

So installing it does not place your application under the GPL. This is the same
reason that compiling proprietary code with GCC, or testing it with a
GPL-licensed tool, does not affect the license of your own code. The tool and
the thing it inspects are separate works.

The cases where it genuinely would matter all involve actually distributing the
code: vendoring the parser into a product you ship, committing `vendor/` with
dev dependencies into something you redistribute, or building and publishing an
image that includes your dev dependencies.

Both drivers exist so that projects with a blanket internal ban on GPL code can
still use this extension. That is a policy constraint rather than a legal one,
and it is a perfectly good reason to pick `iamcal` — but it is not the same as
the GPL requiring anything of you.

This is not legal advice. If your organisation has counsel, they are the right
people to ask about your specific situation.

### Registering your own parser

The driver is resolved through a manager, so you can add a parser of your own
(for a dialect neither package handles well, for instance) by implementing
`CalebDW\PhpstanLaravel\Sql\SqlParser` and registering it:

```php
$manager->extend('postgres', fn () => new MyPostgresSqlParser());
```

Then set `sqlParser: postgres`.

## `disableSchemaScan`

**default**: `false`

You can disable use this config to disable schema scanning.

### Example

```neon
parameters:
    laravel:
        disableSchemaScan: true
```

## `configDirectories`

**default**: `[]`

This extension already knows the types of your configuration values without any
setup. It boots the application to analyse your code, and booting registers every
service provider, which means the config repository in the container holds the
merged configuration of the app and all of its packages. Calls to `config()`, to
the facade — `Config::get()`, `Config::array()`, `Config::collection()`,
`Config::getMany()` — and to the same methods on an injected
`Illuminate\Config\Repository` or its contract are all answered from that live
repository, so **if you are analysing an application there is nothing to
configure here.**

That falls apart in one situation: a package analysed on its own. There is no
application to boot, so nothing publishes or merges the package's own config
files, and the repository knows nothing about them. Rather than making package
authors stand up a full Testbench workbench just to get config types, set
`configDirectories` and the extension will parse those files statically instead.

```neon
parameters:
    laravel:
        configDirectories:
            - config
            - modules/*/config
```

Paths may be absolute or relative to the PHPStan config file that declares them,
and directories are searched recursively. `*` and `?` glob patterns are supported
for the directory portion, which is useful for modular layouts. A file's name is what the first
segment of a config key is matched against, wherever the file sits in the tree —
`modules/billing/config/invoices.php` answers `config('invoices.*')`. If two
files share a name, the first one found wins, in the order the directories are
listed.

The same parameter tells [`NoEnvCallsOutsideOfConfigRule`](rules.md#noenvcallsoutsideofconfigrule)
where `env()` calls are allowed to live, so if you already set it for that rule
you get the type inference for free.

### How a key is resolved

The container is always asked first, and the parsed files only answer keys it
does not have. Setting this option therefore never changes the types you get for
an application's own config — it can only fill in keys that were missing.

### Example

```php
// config/pennant.php
return [
    'default' => 'database',
    'stores' => [
        'database' => ['connection' => null],
    ],
];

// src/Feature.php
\PHPStan\dumpType(config('pennant.default'));           // string|null
\PHPStan\dumpType(config('pennant.stores.database'));    // array{connection: null}|null
\PHPStan\dumpType(config('pennant.missing'));            // mixed
```

Scalar values are widened to their general type — `'database'` becomes `string`
— for the same reason the container path does it: the value in the file is only
the default, and the deployed value can be anything of that type. Array shapes
are kept, since the set of keys is a property of the file rather than of the
environment.

When that is too lossy, annotate the returned array and the declared type is used
verbatim:

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
\PHPStan\dumpType(config('pennant.default')); // 'array'|'database'|null
```

Docblocks are only trusted while PHPStan's own
[`treatPhpDocTypesAsCertain`](https://phpstan.org/config-reference#treatphpdoctypesascertain)
is enabled, which it is by default.

### Performance

Nothing is read until it is needed. The directories are not even scanned unless a
config key turns up missing from the container, each file is parsed at most once
per run, and resolved keys are cached. Leaving this option unset costs nothing at
all.

### Limitations

`config()->all()` and `Config::all()` are answered from the container only, as
returning them would mean parsing every config file and defeat the laziness
described above. Keys whose value is built at runtime — a function call, a match
on the environment — are typed as whatever PHPStan infers for that expression,
which may be `mixed`.

## `checkModelProperties`

**default**: `false`

This config parameter enables the checks for model properties that are passed to methods. You can read the details [here](rules.md#modelpropertyrule).

To enable you can set it to `true`:

```neon
parameters:
    laravel:
        checkModelProperties: true
```

## `checkModelAppends`

**default**: `true`

This config parameter enables the checks the model's $appends property for computed properties. You can read the details [here](rules.md#modelappendsrule).

To disable you can set it to `false`:

```neon
parameters:
    laravel:
        checkModelAppends: false
```

## `checkStrictContracts`

**default**: `false`

By default, when this extension sees a class or interface FQCN passed to
`resolve()`, `app()`, `App::make()`/`App::makeWith()`, or
`Container::make()`/`makeWith()`/`resolve()`, it asks the container what that
identifier is bound to and infers the concrete class that would be returned at
runtime.

That is convenient, but it can hide a real problem: if the binding differs
between environments — production versus testing, or per-tenant — code that
type-hinted an interface can come to rely on methods that only exist on one
particular implementation, and the analysis will happily agree.

Enable this option to take the argument at face value instead. A class or
interface FQCN is inferred as itself, so you only get the API you actually asked
for. Aliases such as `resolve('cache')` are unaffected and still resolve to their
concrete implementation, since they are not class strings.

### Example

```php
use Illuminate\Contracts\Config\Repository;

$repository = resolve(Repository::class);
\PHPStan\dumpType($repository);
```

Disabled (the default), this dumps `Illuminate\Config\Repository` — the concrete
class bound in the container. Enabled, it dumps
`Illuminate\Contracts\Config\Repository`, the interface that was requested.

```neon
parameters:
    laravel:
        checkStrictContracts: true
```
