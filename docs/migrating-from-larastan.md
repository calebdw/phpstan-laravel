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

## 3. Move configuration under `laravel:`

Every option provided by this extension now lives under a `laravel:` key instead of being
mixed into PHPStan's top-level `parameters:`, and the options that switch a rule on or off
sit one level deeper again, under `laravel.rules:`.

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
+        viewDirectories:
+            - resources/views
+        rules:
+            unusedView: true
```

PHPStan's own parameters (`level`, `paths`, `bootstrapFiles`, `ignoreErrors`, and so on)
stay exactly where they are.

The whole tree is schema validated, so a stale or misplaced option fails with an
"Unexpected item" error — usually with the right spelling suggested — rather than being
silently ignored. Nothing in this section can quietly change your analysis: every rename
below removes the old key, so a config you forget to update fails loudly.

### Rule toggles

Under `laravel.rules`, each toggle is named after what the rule reports, with no `check`
or `no` prefix — those prefixes were split roughly half and half across the old options,
and the name of a rule is not the place to restate whether you want it.

| Larastan | Here, under `laravel.rules` |
| --- | --- |
| `checkAuthCallsWhenInRequestScope` | `authInRequestScope` |
| `checkMissingTranslations` | `missingTranslation` |
| `checkModelAppends` | `modelAppends` |
| `checkModelMethodVisibility` | `modelMethodVisibility` |
| `checkOctaneCompatibility` | `octaneCompatibility` |
| `checkUnusedViews` | `unusedView` |
| `noEnvCallsOutsideOfConfig` | `envCallOutsideConfig` |
| `noModelMake` | `modelMake` |
| `noUnnecessaryCollectionCall` | `unnecessaryCollectionCall.enabled` |
| `noUnnecessaryCollectionCallOnly` | `unnecessaryCollectionCall.only` |
| `noUnnecessaryCollectionCallExcept` | `unnecessaryCollectionCall.except` |
| `noUnnecessaryEnumerableToArrayCalls` | `unnecessaryEnumerableToArrayCall` |

Two rules have no Larastan equivalent and so are not in the table:
`modelForwardingToBuilder` and `modelStaticForwardingToBuilder`, both off by
default. See [rules](rules.md#model-forwarding-to-builder).

The three `noUnnecessaryCollectionCall*` options are one rule with two filters, so they
are now one structure:

```diff
 parameters:
     laravel:
-        noUnnecessaryCollectionCall: true
-        noUnnecessaryCollectionCallExcept: ['contains']
+        rules:
+            unnecessaryCollectionCall:
+                enabled: true
+                except: ['contains']
```

`checkModelProperties` stays where it is, directly under `laravel:`. It is not a rule — it
activates the `model-property` type, and the mismatches are then reported by PHPStan's own
argument checks.

### Directory and scanning options

| Larastan | Here |
| --- | --- |
| `databaseMigrationsPath` | `migrationDirectories` |
| `squashedMigrationsPath` | `schemaDirectories` |
| `disableMigrationScan: true` | `scanMigrations: false` |
| `disableSchemaScan: true` | `scanSchema: false` |

Both path options were already lists despite the singular `Path`, and now match
`configDirectories`, `viewDirectories` and `translationDirectories`. "Squashed" also no
longer described what the option pointed at once the scan flag became `scanSchema`.

The two `disable*` flags were the only negatives among the options, which made
`disableMigrationScan: false` a double negative to read. They are now positive and
scanning is the default, so the meaning of the value flips along with the name:

```diff
 parameters:
     laravel:
-        disableMigrationScan: true
-        disableSchemaScan: true
+        scanMigrations: false
+        scanSchema: false
```

Both default to `true`, matching the old defaults of `disableMigrationScan: false` and
`disableSchemaScan: false`. If you never set either, there is nothing to change.

### Four options no longer exist

All four defaulted to `false` in Larastan, so unless you turned one on there is nothing
to do. If you did set one, remove it — the key no longer exists and will fail validation.

| Removed | What happens now |
| --- | --- |
| `checkConfigTypes` | Always on. `config()`, the `Config` facade and an injected repository are always typed from your own config files. |
| `parseModelCastsMethod` | Always on. A model's `casts()` method is always read. |
| `generalizeEnvReturnType` | Gone. `env()` keeps the literal type of its default rather than widening it, which is what `false` already did. |
| `enableMigrationCache` | Gone, along with the cache it controlled. |

The first two were opt-in improvements with no real argument for leaving them off, so
they are simply how the extension behaves. Turning `checkConfigTypes` on by default is
the one that can surface new errors: config values now have real types, so code that was
handed `mixed` is now checked.

### `sqlParser` only accepts the built-in drivers

`sqlParser` is validated against `auto`, `iamcal` and `phpmyadmin`, so a typo fails
configuration validation with the valid values listed instead of failing later, mid-parse.
Registering a parser of your own is no longer supported.

### One rule is now on by default

`unnecessaryEnumerableToArrayCall` was off in Larastan and is on here. It flags
`toArray()` on a collection whose values cannot be `Arrayable`, where `all()` does the
same job without the recursive conversion. It sits alongside `unnecessaryCollectionCall`,
which was already on, so the two redundancy checks now behave alike. Set it to `false` to
restore the old behaviour.

## 4. Error identifiers renamed

Every identifier now uses a `laravel.` prefix and names what was found rather than the
policy behind it, so the `no` prefix is gone from all of them. Where one concern reports
more than one kind of error, the identifiers are grouped under a common stem.

| Larastan | Here |
| --- | --- |
| `larastan.noModelMake` | `laravel.modelMake` |
| `larastan.noEnvCallsOutsideOfConfig` | `laravel.envCallOutsideConfig` |
| `larastan.noUnnecessaryCollectionCall` | `laravel.unnecessaryCollectionCall` |
| `larastan.noAuthFacadeInRequestScope` | `laravel.authInRequestScope.facade` |
| `larastan.noAuthHelperInRequestScope` | `laravel.authInRequestScope.helper` |
| `larastan.noPublicModelScopeMethod` | `laravel.modelMethodVisibility.scope` |
| `larastan.noPublicModelAccessorMethod` | `laravel.modelMethodVisibility.accessor` |
| `larastan.unusedViews` | `laravel.unusedView` |
| `larastan.missingTranslations` | `laravel.missingTranslation` |
| `rules.modelAppends` | `laravel.modelAppends` |
| `larastan.jobs.noConstructor` | `laravel.jobs.noConstructor` |
| `larastan.events.noConstructor` | `laravel.events.noConstructor` |

The rest are a plain prefix swap: `larastan.octaneCompatibility` becomes
`laravel.octaneCompatibility`, and so on for `relationExistence`,
`unnecessaryEnumerableToArrayCall`, `console.*`, `uselessConstructs.*` and
`deferrableServiceProvider.missingProvides`.

Two plurals became singular — `unusedViews` and `missingTranslations` — because each
error is about one view or one translation.

One identifier has no counterpart. `larastan.configCollection` reported
`Config::collection()` on a key that is not an array; that check is now part of a rule
covering every typed accessor, and reports under `laravel.configAccessor`. An
`ignoreErrors` entry for the old identifier can be deleted.

This affects your baseline, any `ignoreErrors` entries using `identifier:`, and inline
`@phpstan-ignore` / `@phpstan-ignore-next-line` comments.

The simplest path by far is to regenerate the baseline:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

Rewriting them in place takes more than a prefix swap, since the `no` comes off, two
names lose a plural, and four are regrouped. The specific cases have to run before the
general ones. Review the diff before committing:

```bash
sed -i -E \
  -e 's/\blarastan\.noAuthFacadeInRequestScope\b/laravel.authInRequestScope.facade/g' \
  -e 's/\blarastan\.noAuthHelperInRequestScope\b/laravel.authInRequestScope.helper/g' \
  -e 's/\blarastan\.noPublicModelScopeMethod\b/laravel.modelMethodVisibility.scope/g' \
  -e 's/\blarastan\.noPublicModelAccessorMethod\b/laravel.modelMethodVisibility.accessor/g' \
  -e 's/\blarastan\.unusedViews\b/laravel.unusedView/g' \
  -e 's/\blarastan\.missingTranslations\b/laravel.missingTranslation/g' \
  -e 's/\blarastan\.no([A-Z])/laravel.\l\1/g' \
  -e 's/\blarastan\./laravel./g' \
  -e 's/\brules\.modelAppends\b/laravel.modelAppends/g' \
  phpstan-baseline.neon
```

Inline ignore comments have to be updated in your source as well. The same script works
on them, with the file list swapped:

```bash
grep -rl '@phpstan-ignore' app src tests | xargs sed -i -E ...
```

> [!TIP]
> Set [`reportUnmatchedIgnoredErrors`][unmatched] to `true` while you migrate. An
> identifier you missed then shows up as an unmatched ignore rather than silently
> ignoring nothing.

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

## 6. Relative directory options resolve differently

The directory options — `configDirectories`, `migrationDirectories`,
`schemaDirectories`, `viewDirectories`, `translationDirectories` — are now
registered with PHPStan's `expandRelativePaths`, so a relative path is resolved
against the config file that declares it. Larastan documented this behaviour but
never wired it up, so relative paths there actually resolved against whichever
directory you happened to run PHPStan from.

If you run PHPStan from your project root with the config file at the root — the
usual setup — nothing changes. It only differs if the declaring config file lives
in a subdirectory, in which case paths that used to be written relative to the
project root should now be written relative to that file (or made absolute with
`%currentWorkingDirectory%`).

## 7. Namespace change

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
[unmatched]: https://phpstan.org/user-guide/ignoring-errors#reporting-unused-ignores
[extension-installer]: https://phpstan.org/user-guide/extension-library#installing-extensions
[can]: https://github.com/canvural
[nuno]: https://github.com/nunomaduro
