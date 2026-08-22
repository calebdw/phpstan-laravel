# Migrating from Larastan

This package started life as a fork of [larastan/larastan][larastan] and carries the same
analysis features, rules, and stubs. It has since been renamed and split off as its own
package, so migrating takes a handful of mechanical changes.

## Requirements

| | Larastan 3.x | This package |
| --- | --- | --- |
| PHP | `^8.2` | `^8.3` |
| Laravel | 11, 12, 13 | 12, 13 |

Laravel 11 is no longer supported. Only the two most recent Laravel releases are supported,
and each is required at a recent minor version (`^12.67.0 \|\| ^13.26.1`) rather than the
oldest release of that major.

## 1. Swap the package

```bash
composer remove --dev larastan/larastan
composer require --dev calebdw/phpstan-laravel
```

If you were using the `calebdw/larastan` fork, remove that instead:

```bash
composer remove --dev calebdw/larastan
composer require --dev calebdw/phpstan-laravel
```

> [!IMPORTANT]
> Unlike `calebdw/larastan`, this package does **not** declare `replace` for
> `larastan/larastan`. Make sure the old package is actually removed — if both are
> installed the extension is registered twice and you will get duplicate errors.

## 2. Update the include path

If you use the [PHPStan extension installer][extension-installer] there is nothing to do.
Otherwise update the include in your `phpstan.neon`:

```diff
 includes:
-    - vendor/larastan/larastan/extension.neon
+    - vendor/calebdw/phpstan-laravel/extension.neon
```

## 3. Nest configuration under `laravel:`

Every configuration option provided by this extension now lives under a `laravel:` key
instead of being mixed into PHPStan's top-level `parameters:`.

```diff
 parameters:
     level: 8
     paths:
         - app
-    checkModelProperties: true
-    checkUnusedViews: true
-    viewDirectories:
-        - resources/views
+    laravel:
+        checkModelProperties: true
+        checkUnusedViews: true
+        viewDirectories:
+            - resources/views
```

This applies to every option provided by this extension. PHPStan's own parameters (`level`,
`paths`, `bootstrapFiles`, `ignoreErrors`, and so on) stay exactly where they are.

Nesting is validated, so a stale top-level option will fail with an "Unexpected item"
error rather than being silently ignored.

## 4. Error identifiers renamed

All error identifiers now use a `laravel.` prefix:

```diff
- larastan.noModelMake
+ laravel.noModelMake
```

Two cases do not follow a plain prefix swap:

| Old | New |
| --- | --- |
| `rules.modelAppends` | `laravel.modelAppends` |
| `larastan.jobs.noConstructor`, `larastan.events.noConstructor` | `laravel.jobs.noConstructor`, `laravel.events.noConstructor` |

This affects your baseline, any `ignoreErrors` entries using `identifier:`, and inline
`@phpstan-ignore` / `@phpstan-ignore-next-line` comments.

The simplest path is to regenerate the baseline:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

If you would rather not regenerate it, rewrite the identifiers in place:

```bash
sed -i 's/\blarastan\./laravel./g; s/\brules\.modelAppends\b/laravel.modelAppends/g' phpstan-baseline.neon
```

Inline ignore comments have to be updated in your source as well. Review the diff before
committing:

```bash
grep -rl '@phpstan-ignore' app src tests \
    | xargs sed -i 's/\blarastan\./laravel./g; s/\brules\.modelAppends\b/laravel.modelAppends/g'
```

## 5. Install an SQL parser if you use schema dumps

Larastan required a parser for squashed schema dumps outright — `phpmyadmin/sql-parser`
in the `calebdw/larastan` fork, `iamcal/sql-parser` upstream. Neither is a hard
requirement here, so that the license entering your dependency tree is your
choice rather than this package's:

```bash
composer require --dev iamcal/sql-parser      # MIT
composer require --dev phpmyadmin/sql-parser  # GPL-2.0-or-later
```

If you have no dumps under `database/schema`, you need neither — the parser is
only resolved when there is a dump to read. Otherwise analysis fails with
instructions telling you to install one. Use
[`sqlParser`](custom-config-parameters.md#sqlparser) to name a driver explicitly
instead of letting it pick.

Two schema types are now inferred more precisely, which may surface new errors
in code that relied on the looser types:

- `UNSIGNED` integer columns are `non-negative-int` rather than `int`
- `ENUM` columns are a union of their literal values rather than `string`

A dump that creates the same table more than once (`DROP TABLE IF EXISTS`
followed by `CREATE TABLE`, repeated) now resolves to the **last** definition
rather than the first, matching what replaying the dump would actually leave you
with.

## 6. Namespace change

The PHP namespace changed from `Larastan\Larastan\` to `CalebDW\PhpstanLaravel\`. This only
matters if you reference the extension's classes directly — for example a custom rule that
extends one of them, or your own service definitions:

```diff
 services:
     -
-        class: Larastan\Larastan\Methods\BuilderHelper
+        class: CalebDW\PhpstanLaravel\Methods\BuilderHelper
```

## Credits

Larastan was created by [Can Vural][can] and [Nuno Maduro][nuno], and improved by many
contributors over the years. This package builds directly on that work and remains
MIT licensed.

<!-- links -->
[larastan]: https://github.com/larastan/larastan
[extension-installer]: https://phpstan.org/user-guide/extension-library#installing-extensions
[can]: https://github.com/canvural
[nuno]: https://github.com/nunomaduro
