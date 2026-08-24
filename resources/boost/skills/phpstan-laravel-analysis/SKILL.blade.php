---
name: phpstan-laravel-analysis
description: "Runs and interprets static analysis for Laravel projects using calebdw/phpstan-laravel. Activates when running PHPStan, reading or resolving PHPStan errors, configuring phpstan.neon, deciding whether to ignore an error or add a baseline entry, working with model property or relation inference, or when the user mentions PHPStan, static analysis, Larastan, or type errors in a Laravel codebase."
license: MIT
---
@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# PHPStan Laravel Analysis

`calebdw/phpstan-laravel` is a PHPStan extension for Laravel. It infers model
properties from migrations and schema dumps, understands relations, custom
builders, factories, facades, container bindings and config values, and adds
rules for Laravel-specific mistakes.

## Running it

```bash
{{ $assist->binCommand('phpstan') }} analyse
{{ $assist->binCommand('phpstan') }} analyse --error-format=json   # to parse
{{ $assist->binCommand('phpstan') }} analyse path/to/File.php      # narrow scope
```

Analysis is cached. After changing `phpstan.neon`, migrations, or anything that
feeds inference, add `--clear-cache` if results look stale.

The extension boots the application in order to answer questions about the
container and config. A failure can therefore be a real bootstrap problem
rather than a type error. Read the message before assuming which.

Booting runs every service provider's `register()` and `boot()`, constructs
anything those providers resolve along with its constructor dependencies, and
constructs every console command. All of that happens on every analysis run. Do
not add side effects to a provider, to a command's constructor, or to the
constructor of anything they resolve: no writing files, no network calls, no
sending mail, nothing slow. When work genuinely
cannot be skipped or deferred, guard it with the constant PHPStan defines:

```php
if (defined('__PHPSTAN_RUNNING__')) {
    return;
}
```

## Resolving what it reports

Fix the code. Reach for a suppression only when the report is genuinely wrong,
and only with the user's approval. In particular, do not reflexively add:

- `@phpstan-ignore` comments or baseline entries
- `assert()` calls or inline `@var` tags to override the inferred type
- type casts added purely to silence a message
- widened parameter or return types

Each of those hides the finding rather than answering it, and the widened
signature in particular pushes the problem onto every caller.

## Identifiers

Every error from this extension carries a `laravel.` prefixed identifier, shown
in the output. When an ignore is warranted and approved, prefer the identifier
over a bare line suppression, so that unrelated errors on the same line still
surface:

```neon
parameters:
    ignoreErrors:
        - identifier: laravel.modelMake
```

## Configuration

This extension's options nest under `parameters.laravel`, unlike PHPStan's own
options, which stay at the top level. Options that switch a rule on or off nest
one level deeper, under `parameters.laravel.rules`. The whole tree is schema
validated, so a misplaced or misspelled key fails the run instead of being
silently ignored.

```neon
parameters:
    level: 8
    paths:
        - app
    laravel:
        modelPropertyType: true
        rules:
            unusedView: true
```

Options worth knowing:

- `modelPropertyType` (off) verifies property names against the columns
  resolved from migrations. The highest-value option, off by default only
  because it depends on that column list being complete. It is not a rule, so
  it does not live under `rules`.
- `configDirectories` lets config values be typed by parsing files, which is
  what a package without a bootable application needs.
- `migrationDirectories` and `schemaDirectories` point at migrations and schema
  dumps kept outside the default locations. A configured list replaces the
  default, so include `database/migrations` or `database/schema` in it to retain
  the conventional directory. Migration paths scan direct files only; nested
  directories require their own path or wildcard.
- `scanMigrations` and `scanSchema` (both on) turn that scanning off.

Rule toggles under `rules` are named after what the rule reports and match the
identifier suffix: `laravel.rules.modelMake` switches off the rule that reports
`laravel.modelMake`.

## Model property inference

Properties are resolved by scanning migrations and schema dumps. When a
property is reported as missing but plainly exists, the usual cause is that the
extension could not see how the table was built: migrations in an unusual
place, a table created by something the scanner cannot follow, or a schema dump
it could not parse. Point the path options at the right directories rather than
annotating around the gap.

Squashed schema dumps need an SQL parser, which is not a hard dependency:

```bash
{{ $assist->composerCommand('require --dev iamcal/sql-parser') }}
```
