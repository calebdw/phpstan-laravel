---
name: phpstan-laravel-larastan-migration
description: "Performs the migration from larastan/larastan or calebdw/larastan to calebdw/phpstan-laravel. Activates when the user asks to migrate, switch, move off, or replace Larastan, when both packages are installed at once, when a phpstan.neon still carries Larastan-era options such as checkModelProperties, checkUnusedViews, databaseMigrationsPath or disableMigrationScan, or when a baseline or @phpstan-ignore comment still uses a larastan. prefixed identifier."
license: MIT
---
@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# Migrating from Larastan

`calebdw/phpstan-laravel` began as a fork of `larastan/larastan` and carries the
same analysis features, rules and stubs. Everything below is mechanical: a
package swap, a set of renames, and a verification pass.

Work through the steps in order. Each one is independently checkable, and the
identifier rewrite in step 4 assumes the config rewrite in step 3 has already
happened.

## 0. Before touching anything

Commit or stash outstanding work first. This migration edits `composer.json`,
`phpstan.neon`, the baseline, and potentially every source file with an inline
ignore comment, and a reviewable diff is the main safety net.

Record the starting point, because the whole point of the last step is to
compare against it:

```bash
{{ $assist->binCommand('phpstan') }} analyse --error-format=json > /tmp/phpstan-before.json
```

If that run already fails, note why. A pre-existing failure is not something
the migration will fix, and mistaking one for a migration regression wastes the
rest of the effort.

Check the requirements while you are here. PHP must be `^8.3` and Laravel
`^12.67.0 || ^13.26.1`. Laravel 11 is not supported, and only the two most
recent Laravel releases are, each at a recent minor rather than the oldest
release of that major. If the project is on Laravel 11 or PHP 8.2, stop and
tell the user: that upgrade comes first and is not part of this skill.

## 1. Swap the package

Find which one is installed. It is `larastan/larastan`, or the
`calebdw/larastan` fork, and occasionally both:

```bash
{{ $assist->composerCommand('show --direct') }} | grep -i larastan
```

Remove what you found and require the replacement:

```bash
{{ $assist->composerCommand('remove --dev larastan/larastan') }}
{{ $assist->composerCommand('require --dev calebdw/phpstan-laravel') }}
```

The old package must actually be gone rather than sitting alongside the new
one. With both installed the extension is registered twice and every error is
reported twice, which looks like a catastrophic regression and is not one.

## 2. Update the include path

Nothing to do if the project uses `phpstan/extension-installer`. Check
`composer.json` for it. Otherwise update the include in `phpstan.neon` (or
`phpstan.neon.dist`, or both):

```diff
 includes:
-    - vendor/larastan/larastan/extension.neon
+    - vendor/calebdw/phpstan-laravel/extension.neon
```

## 3. Move configuration under `laravel:`

Every option this extension provides now lives under a `laravel:` key instead
of being mixed into PHPStan's top-level `parameters:`, and the options that
switch a rule on or off sit one level deeper again, under `laravel.rules:`.

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
+        modelPropertyType: true
+        viewDirectories:
+            - resources/views
+        rules:
+            unusedView: true
```

PHPStan's own parameters stay exactly where they are: `level`, `paths`,
`bootstrapFiles`, `ignoreErrors`, `excludePaths`, `treatPhpDocTypesAsCertain`
and the rest. Only move keys that appear in the tables below.

The whole `laravel:` tree is schema validated, so you cannot silently
mis-migrate an option. A stale, misplaced or misspelled key fails the run with
an "Unexpected item" error, usually suggesting the right spelling. That means
step 8 will catch anything missed here, and it also means a half-finished
config fails loudly rather than quietly analysing less than before.

### Rule toggles

These move under `laravel.rules` and lose their `check` or `no` prefix:

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
| `noUnnecessaryEnumerableToArrayCalls` | `unnecessaryEnumerableToArrayCall` |

Two more exist only in the `calebdw/larastan` fork, so they apply only if that
is where the project is coming from. Both stay off by default:

| `calebdw/larastan` | Here, under `laravel.rules` |
| --- | --- |
| `noModelForwardingToBuilder` | `modelForwardingToBuilder` |
| `noModelStaticForwardingToBuilder` | `modelStaticForwardingToBuilder` |

The three `noUnnecessaryCollectionCall*` options were one rule with two
filters, and are now one structure:

```diff
 parameters:
-    noUnnecessaryCollectionCall: true
-    noUnnecessaryCollectionCallExcept: ['contains']
+    laravel:
+        rules:
+            unnecessaryCollectionCall:
+                enabled: true
+                except: ['contains']
```

`noUnnecessaryCollectionCallOnly` becomes `unnecessaryCollectionCall.only` the
same way.

### `checkModelProperties` is not a rule

It is renamed to `modelPropertyType` and stays directly under `laravel:`, not
under `rules:`:

```diff
 parameters:
     laravel:
-        checkModelProperties: true
+        modelPropertyType: true
```

It switches on the `model-property` type; the mismatches are then reported by
PHPStan's own argument checks, so they carry core identifiers rather than a
`laravel.` one. Do not put it under `rules:` — validation will reject it.

### Directory and scanning options

| Larastan | Here, under `laravel` |
| --- | --- |
| `databaseMigrationsPath` | `migrationDirectories` |
| `squashedMigrationsPath` | `schemaDirectories` |
| `disableMigrationScan: true` | `scanMigrations: false` |
| `disableSchemaScan: true` | `scanSchema: false` |

The two scan flags flip meaning along with the name, so invert the value:

```diff
 parameters:
     laravel:
-        disableMigrationScan: true
-        disableSchemaScan: true
+        scanMigrations: false
+        scanSchema: false
```

`scanMigrations` and `scanSchema` both default to `true`, matching the old
defaults of `disableMigrationScan: false` and `disableSchemaScan: false`. If
neither `disable*` flag was ever set, add nothing.

### Four options no longer exist

All four defaulted to `false` in Larastan, so if none were set there is nothing
to do. If one was set, delete it — the key will fail validation.

| Removed | What happens now |
| --- | --- |
| `checkConfigTypes` | Always on. `config()`, the `Config` facade and an injected repository are always typed from the project's own config files. |
| `parseModelCastsMethod` | Always on. A model's `casts()` method is always read. |
| `generalizeEnvReturnType` | Gone. `env()` keeps the literal type of its default, which is what `false` already did. |
| `enableMigrationCache` | Gone, along with the cache it controlled. |

`checkConfigTypes` being on by default is the one that can surface new errors,
since config values now have real types where code was previously handed
`mixed`. Expect that in step 8 rather than treating it as a break.

### `sqlParser` no longer accepts a custom driver

It is validated against `auto`, `iamcal`, `phpmyadmin` and `postgres`.
Registering a parser class of your own is not supported. If the project did
that, the custom parser has to go and one of the four drivers takes its place.

## 4. Rewrite error identifiers

Every identifier now carries a `laravel.` prefix and names what was found
rather than the policy behind it, so the `no` prefix is gone. This affects the
baseline, any `ignoreErrors` entry using `identifier:`, and every inline
`@phpstan-ignore` or `@phpstan-ignore-next-line` comment in the source.

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
| `larastan.noModelForwardingToBuilder` | `laravel.modelForwardingToBuilder` |
| `larastan.noModelStaticForwardingToBuilder` | `laravel.modelStaticForwardingToBuilder` |

The rest are a plain prefix swap: `larastan.octaneCompatibility` becomes
`laravel.octaneCompatibility`, and likewise for `relationExistence`,
`unnecessaryEnumerableToArrayCall`, `console.*`, `uselessConstructs.*` and
`deferrableServiceProvider.missingProvides`.

One identifier has no counterpart. `larastan.configCollection` reported
`Config::collection()` on a key that is not an array; that check is now part of
a rule covering every typed accessor and reports under `laravel.configAccessor`.
Delete the old entry rather than renaming it.

Rewrite in place rather than regenerating the baseline. Regenerating folds the
genuinely new errors from step 8 into the baseline as well, which is precisely
the information worth seeing. The specific cases have to run before the general
ones, so keep the expressions in this order and apply the same set to every
file:

```bash
rewrite_identifiers() {
    [ "$#" -eq 0 ] && return 0
    sed -i -E \
        -e 's/\blarastan\.noEnvCallsOutsideOfConfig\b/laravel.envCallOutsideConfig/g' \
        -e 's/\blarastan\.noAuthFacadeInRequestScope\b/laravel.authInRequestScope.facade/g' \
        -e 's/\blarastan\.noAuthHelperInRequestScope\b/laravel.authInRequestScope.helper/g' \
        -e 's/\blarastan\.noPublicModelScopeMethod\b/laravel.modelMethodVisibility.scope/g' \
        -e 's/\blarastan\.noPublicModelAccessorMethod\b/laravel.modelMethodVisibility.accessor/g' \
        -e 's/\blarastan\.unusedViews\b/laravel.unusedView/g' \
        -e 's/\blarastan\.missingTranslations\b/laravel.missingTranslation/g' \
        -e 's/\blarastan\.no([A-Z])/laravel.\l\1/g' \
        -e 's/\blarastan\./laravel./g' \
        -e 's/\brules\.modelAppends\b/laravel.modelAppends/g' \
        "$@"
}

rewrite_identifiers phpstan-baseline.neon
rewrite_identifiers $(grep -rl '@phpstan-ignore' app src tests 2>/dev/null)
```

Adjust both invocations to the paths the project actually has: the baseline may
be named differently or not exist, and the source directories vary.

Run the identifier rewrite on the baseline and the source, not on
`phpstan.neon`. The last expression would otherwise turn a freshly written
`laravel.rules.modelAppends` config key into `laravel.laravel.modelAppends`.
Any `identifier:` entries under `ignoreErrors` in `phpstan.neon` need the same
renames applied by hand.

Turn on unmatched-ignore reporting for the duration, so an identifier you missed
shows up as an unmatched ignore instead of silently ignoring nothing:

```neon
parameters:
    reportUnmatchedIgnoredErrors: true
```

It is the default, so this is only needed if the project explicitly set it to
`false`. Leave it on if you can; if the user wants it back off, do that after
step 8 passes.

## 5. Install an SQL parser if the project has schema dumps

Larastan required a parser outright. Here they are optional dependencies, so
which license enters the dependency tree is the user's choice:

```bash
{{ $assist->composerCommand('require --dev iamcal/sql-parser') }}        # MIT
{{ $assist->composerCommand('require --dev phpmyadmin/sql-parser') }}    # GPL-2.0-or-later
{{ $assist->composerCommand('require --dev calebdw/pg-schema-parser') }} # MIT, PostgreSQL
```

Check for dumps under `database/schema` first. With none, no parser is needed —
one is resolved only when there is a dump to read. If the project has dumps and
was on `calebdw/larastan` it already had `phpmyadmin/sql-parser`; upstream
Larastan had `iamcal/sql-parser`. Keeping the same one is the least surprising
choice; do not switch a project to a GPL parser without asking.

Two schema types are now inferred more precisely, which can surface new errors
in code that relied on the looser types:

- `UNSIGNED` integer columns are `non-negative-int` rather than `int`
- `ENUM` columns are a union of their literal values rather than `string`

A dump that creates the same table more than once now resolves to the **last**
definition rather than the first, matching what replaying the dump would leave
behind.

## 6. Check relative directory paths

The directory options — `configDirectories`, `migrationDirectories`,
`schemaDirectories`, `viewDirectories`, `translationDirectories` — now resolve a
relative path against the config file that declares it. Larastan documented
this but never wired it up, so relative paths there resolved against whatever
directory PHPStan was invoked from.

Nothing changes for the usual setup: config file at the project root, PHPStan
run from the project root. It matters only when the declaring config file lives
in a subdirectory, where a path written relative to the project root now needs
to be relative to that file, or absolute via `%currentWorkingDirectory%`.

## 7. Namespace change

`Larastan\Larastan\` became `CalebDW\PhpstanLaravel\`. This matters only where
the extension's classes are referenced directly — a custom rule extending one,
or a service definition:

```diff
 services:
     -
-        class: Larastan\Larastan\Methods\BuilderHelper
+        class: CalebDW\PhpstanLaravel\Methods\BuilderHelper
```

Sweep for anything left over, including CI config, scripts, editor settings and
documentation:

```bash
grep -rni larastan --exclude-dir=vendor --exclude-dir=node_modules .
```

Every remaining hit should be either deliberate prose about the migration or
something to fix. `composer.lock` will be clean already from step 1.

## 8. Verify

```bash
{{ $assist->binCommand('phpstan') }} analyse --clear-cache
```

Read the failure mode before reading the errors:

- An "Unexpected item" or unknown-parameter failure means a config key from
  step 3 was missed or misplaced. The message usually names the right spelling.
- A bootstrap failure is not a type error. The extension boots the application
  to answer questions about the container and config, so this can be a real
  application problem that Larastan never exercised.
- Unmatched ignored errors mean identifiers from step 4 were missed, or that a
  baseline entry is now genuinely obsolete. Distinguish the two before deleting
  anything.

Then compare the errors against `/tmp/phpstan-before.json`. New errors are
expected, and this package treats them as improvements in the analysis rather
than breaking changes. The likely sources, in rough order of how often they
come up:

- `checkConfigTypes` is always on now, so config values that used to be `mixed`
  are checked.
- `unnecessaryEnumerableToArrayCall` is on by default here and was off in
  Larastan. It flags `toArray()` on a collection whose values cannot be
  `Arrayable`, where `all()` does the same job without the recursive
  conversion. Setting it to `false` under `laravel.rules` restores the old
  behaviour.
- `non-negative-int` and enum literal unions from schema dumps.
- Better inference generally: relations, custom builders, factories, facades.

Fix them. Do not baseline the new errors to get a green run, and do not reach
for `@phpstan-ignore`, `assert()`, inline `@var`, a cast or a widened signature
to make one go away. If the volume is genuinely too large to work through now,
say so and let the user decide between fixing, disabling a specific rule, or
adding a scoped baseline — that is their call, not a default.

Report at the end: which package was removed, which options were renamed or
dropped, how many identifiers were rewritten, and the before/after error counts
with the new errors grouped by cause.
