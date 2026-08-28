# Custom config parameters

Every option this extension defines lives under `parameters.laravel` in your
PHPStan configuration:

```neon
parameters:
    laravel:
        modelPropertyType: true
        rules:
            unusedView: true
```

PHPStan's own parameters---`level`, `paths`, `bootstrapFiles`, `ignoreErrors`
and so on---stay at the top level. The nesting is validated, so a misspelled or
misplaced option fails with an "Unexpected item" error rather than being
silently ignored.

There are two groups. **Rule toggles** live under `laravel.rules` and are
documented with the rule they switch on, in [rules](../rules/index.md). Everything else
is on this page: where to look for your schema, and how types are inferred.

## Rule toggles

Each of these enables or disables one rule. Follow the link for what the rule
reports, its error identifier, and its own options.

| Option under `laravel.rules` | Default | Rule |
| --- | --- | --- |
| `authInRequestScope` | `false` | [Auth in request scope](../rules/framework.md#auth-in-request-scope) |
| `configAccessor` | `true` | [Config accessor](../rules/config.md#config-accessor) |
| `envCallOutsideConfig` | `true` | [Env call outside config](../rules/config.md#env-call-outside-config) |
| `missingTranslation` | `false` | [Missing translation](../rules/views-and-translations.md#missing-translation) |
| `modelAppends` | `true` | [Model appends](../rules/eloquent.md#model-appends) |
| `modelForwardingToBuilder` | `false` | [Model forwarding to builder](../rules/eloquent.md#model-forwarding-to-builder) |
| `modelMake` | `true` | [Model make](../rules/eloquent.md#model-make) |
| `modelMethodVisibility` | `false` | [Model method visibility](../rules/eloquent.md#model-method-visibility) |
| `modelStaticForwardingToBuilder` | `false` | [Model static forwarding to builder](../rules/eloquent.md#model-static-forwarding-to-builder) |
| `octaneCompatibility` | `false` | [Octane compatibility](../rules/framework.md#octane-compatibility) |
| `undefinedConfigName` | `false` | [Undefined config name](../rules/config.md#undefined-config-name) |
| `unnecessaryCollectionCall` | `true` | [Unnecessary collection call](../rules/collections.md#unnecessary-collection-call) |
| `unnecessaryEnumerableToArrayCall` | `true` | [Unnecessary enumerable toArray call](../rules/collections.md#unnecessary-enumerable-toarray-call) |
| `unusedView` | `false` | [Unused view](../rules/views-and-translations.md#unused-view) |

`unnecessaryCollectionCall` is the one toggle that is a structure rather than a
plain boolean, because it takes method filters as well:

```neon
parameters:
    laravel:
        rules:
            unnecessaryCollectionCall:
                enabled: true
                only: []
                except: []
```

Rules that are not configurable at all---they report unconditionally---are
listed in [rules](../rules/index.md) alongside the rest.

## `migrationDirectories`

**default**: `database/migrations`

Migration files are scanned to work out your table structure, which is where
model properties come from. When this option is empty, the extension scans
Laravel's `database_path('migrations')` directory.

A non-empty list replaces that default; it does not append to it. This lets a
project scan only migrations kept elsewhere. To scan additional directories
while retaining `database/migrations`, include the default path in the list.

Paths may be absolute or relative to the PHPStan config file that declares
them, and `glob` wildcards are supported. As with Laravel's migrator, only PHP
files directly inside each matched directory are scanned. Nested directories
are not scanned implicitly.

### Replace the default

```neon
parameters:
    laravel:
        migrationDirectories:
            - app/Domain/*/migrations
```

Only the matching domain migration directories are scanned in this example.

### Add to the default

```neon
parameters:
    laravel:
        migrationDirectories:
            - database/migrations
            - app/Domain/*/migrations
```

Both the conventional application migrations and the matching domain
migrations are scanned in this example.

### Scan nested directories

Add a wildcard directory path when migrations are intentionally grouped into
subdirectories:

```neon
parameters:
    laravel:
        migrationDirectories:
            - database/migrations
            - database/migrations/*
```

The first path scans migrations directly in `database/migrations`; the second
scans migrations directly inside each of its immediate subdirectories. Without
the second path, directories such as `database/migrations/archive` are ignored.

**Note:** If your migrations are using `if` statements to conditionally alter database structure (ex: create table only if it's not there, add column only if table exists and column does not etc...) this extension will assume those if statements evaluate to true and will consider everything from the `if` body.

## `scanMigrations`

**default**: `true`

Migration files are scanned to infer model properties from your table
structure. Set this to `false` to skip the scan, for instance when your models
already carry `@property` annotations and you would rather not pay to parse
migrations you do not need.

### Example

```neon
parameters:
    laravel:
        scanMigrations: false
```

## `schemaDirectories`

**default**: `database/schema`

Squashed schema dumps are read for the same reason migrations are. When this
option is empty, the extension scans Laravel's `database_path('schema')`
directory.

A non-empty list replaces that default; it does not append to it. To scan
additional directories while retaining `database/schema`, include the default
path in the list.

Paths may be absolute or relative to the PHPStan config file that declares
them, and `glob` wildcards are supported.

### Replace the default

```neon
parameters:
    laravel:
        schemaDirectories:
            - app/Domain/*/schema
```

Only the matching domain schema directories are scanned in this example.

### Add to the default

```neon
parameters:
    laravel:
        schemaDirectories:
            - database/schema
            - app/Domain/*/schema
```

Both the conventional schema directory and the matching domain schema
directories are scanned in this example.

### PostgreSQL

PostgreSQL plain-text dumps are supported by the `postgres` driver. Custom and
directory-format dumps are not SQL text and cannot be scanned.

## `sqlParser`

**default**: `auto`

Selects which SQL parser reads your squashed schema dumps. The parsers are
optional dependencies:

```bash
composer require --dev iamcal/sql-parser      # MIT
composer require --dev phpmyadmin/sql-parser  # GPL-2.0-or-later
composer require --dev calebdw/pg-schema-parser # MIT, PostgreSQL
```

| Driver | Uses | Notes |
| --- | --- | --- |
| `auto` | whichever is installed | Prefers `phpmyadmin`, then `iamcal`, then `postgres` |
| `iamcal` | `iamcal/sql-parser` | MIT, no dependencies of its own |
| `phpmyadmin` | `phpmyadmin/sql-parser` | GPL-2.0-or-later, understands more of the MySQL dialect |
| `postgres` | `calebdw/pg-schema-parser` | MIT, parses PostgreSQL plain-text dumps |

These four are the only accepted values; anything else fails configuration
validation with the valid ones listed.

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

`auto` selects `postgres` when it is the only installed parser. If both a MySQL
parser and the PostgreSQL parser are installed, set the driver explicitly
because the dump does not identify its dialect before parsing.

If you do not use squashed schema dumps at all, you need neither package: the
parser is only resolved when there is a dump to read. You can also set
[`scanSchema`](#scanschema) to `false` to skip them entirely.

### A note on the GPL-2.0 parser

`phpmyadmin/sql-parser` is GPL-2.0-or-later, which puts people off more than it
should. It is worth being precise about what that license actually requires.

The GPL's copyleft obligations are triggered by **distributing** the licensed
code, or a derivative work of it. A development-only static analysis dependency
is neither. It is not linked into your application, it is not shipped with it,
and `composer install --no-dev`---what you run to build a production install---leaves it out of the dependency tree entirely. Nothing you deploy contains any
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
and it is a perfectly good reason to pick `iamcal`, but it is not the same as
the GPL requiring anything of you.

This is not legal advice. If your organisation has counsel, they are the right
people to ask about your specific situation.

## `scanSchema`

**default**: `true`

Squashed schema dumps are scanned to infer model properties. Set this to
`false` to skip them, which also removes the need for an SQL parser to be
installed at all.

A dump the parser cannot read fails the analysis rather than being skipped,
since the tables it defines would otherwise go missing from model properties
with nothing to say so. Setting this to `false` is the way to opt out of a
dump you cannot fix.

### Example

```neon
parameters:
    laravel:
        scanSchema: false
```

## `configDirectories`

**default**: `[]`

This extension already knows the types of your configuration values without any
setup. It boots the application to analyse your code, and booting registers every
service provider, which means the config repository in the container holds the
merged configuration of the app and all of its packages. Calls to `config()`, to
the facade---`Config::get()`, `Config::array()`, `Config::collection()`,
`Config::getMany()`---and to the same methods on an injected
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

The same parameter tells [the env-call rule](../rules/config.md#env-call-outside-config)
where `env()` calls are allowed to live, so if you already set it for that rule
you get the type inference for free.

### How a key is resolved

The container is always asked first, and the parsed files only answer keys it
does not have. Setting this option therefore never changes the types you get for
an application's own config: it can only fill in keys that were missing.

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

Scalar values are widened to their general type: `'database'` becomes `string`
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
described above. Keys whose value is built at runtime---a function call, a match
on the environment---are typed as whatever PHPStan infers for that expression,
which may be `mixed`.

## `viewDirectories`

**default**: `[]`

Where to look for Blade files. Left unset, the paths and namespace hints
registered with Laravel's view finder are used, which covers a standard
application and any package that registers its own views. Set this when views
live somewhere the finder does not know about.

```neon
parameters:
    laravel:
        viewDirectories:
            - domainA/resources/views
            - a/path/to/views
```

Setting it replaces the finder's list rather than adding to it, so include every
directory you want searched.

This is the list the [unused view](../rules/views-and-translations.md#unused-view) rule searches, and
where views referenced from inside another view are looked up.

Paths may be absolute or relative to the PHPStan config file that declares
them. Unlike the migration and schema options, these are plain directories —
`glob` wildcards are not expanded.

## `translationDirectories`

**default**: `[]`

Where to look for translation files. Left unset, the application's `lang_path()`
is used.

```neon
parameters:
    laravel:
        translationDirectories:
            - resources/lang
            - resources/translations
```

Setting it replaces `lang_path()` rather than adding to it, so list every
directory including the default one if you still want it searched.

Used by the [missing translation](../rules/views-and-translations.md#missing-translation) rule. A
directory it cannot see is indistinguishable from a translation that was never
written, so register all of them or leave that rule off.

Paths may be absolute or relative to the PHPStan config file that declares
them. Unlike the migration and schema options, these are plain directories —
`glob` wildcards are not expanded.

## `staticMacroClasses`

**default**:

```neon
- Illuminate\Support\Arr
- Illuminate\Support\Str
- Illuminate\Support\Number
- Illuminate\Support\Benchmark
- Illuminate\Validation\Rule
```

Macros on these classes may be called statically even when registered with a
non-static closure. Entries apply to subclasses and only affect dynamically
discovered macros; native instance methods retain normal PHP static-call checks.

Lists are merged across included NEON files, so adding a class retains these
defaults. Use `staticMacroClasses!` to replace the list, or
`staticMacroClasses!: []` to remove every default. Facades remain static-facing
regardless of this option. See the [macros guide](../guide/macros.md) for examples
and the tradeoff involved in adding Eloquent models.

## `modelPropertyType`

**default**: `false`

Checks string arguments that are meant to name a column against the model's
actual columns, so a typo is caught where it is written rather than at runtime.

```neon
parameters:
    laravel:
        modelPropertyType: true
```

This is not a rule and has no identifier of its own. It activates the
[`model-property`](../guide/custom-types.md) type, after which the mismatches are
reported by PHPStan's ordinary argument checks, so they carry core identifiers
such as `argument.type`. Laravel's own methods that expect a column are
annotated for you; you can annotate your own the same way.

Whether it is accurate depends on how completely your columns were resolved.
Where migrations or schema dumps are missing, or a table is built in a way the
scanner cannot follow, the gap surfaces as a false positive rather than as
silence, which is why it is off by default. Point
[`migrationDirectories`](#migrationdirectories) and
[`schemaDirectories`](#schemadirectories) at the right places before enabling
it. [Checking column names](../guide/model-properties.md#checking-column-names) has a worked example.

## `strictContracts`

**default**: `false`

By default, when this extension sees a class or interface FQCN passed to
`resolve()`, `app()`, `App::make()`/`App::makeWith()`, or
`Container::make()`/`makeWith()`/`resolve()`, it asks the container what that
identifier is bound to and infers the concrete class that would be returned at
runtime.

That is convenient, but it can hide a real problem: if the binding differs
between environments---production versus testing, or per-tenant---code that
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

Disabled (the default), this dumps `Illuminate\Config\Repository`: the concrete
class bound in the container. Enabled, it dumps
`Illuminate\Contracts\Config\Repository`, the interface that was requested.

```neon
parameters:
    laravel:
        strictContracts: true
```
