<h1 align="center">PHPStan Laravel</h1>

<p align="center">
  <strong>Static analysis for Laravel applications.</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://img.shields.io/packagist/v/calebdw/phpstan-laravel.svg" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://img.shields.io/packagist/dt/calebdw/phpstan-laravel.svg" alt="Total Downloads"></a>
  <a href="https://github.com/calebdw/phpstan-laravel/actions/workflows/tests.yml"><img src="https://github.com/calebdw/phpstan-laravel/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://badge.laravel.cloud/badge/calebdw/phpstan-laravel" alt="Laravel Compatibility"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/calebdw/phpstan-laravel" alt="License"></a>
</p>

------

A [PHPStan][phpstan] extension that teaches the analyser how Laravel actually works.

Laravel leans heavily on magic — facades, container bindings, dynamic Eloquent properties,
method forwarding, macros. Out of the box a static analyser sees very little of it. This
extension bridges that gap by booting your application during analysis and combining that
with stubs, reflection extensions, and schema scanning, so PHPStan can reason about your
models, relations, collections, config, and views.

It also ships a set of Laravel-specific rules that catch mistakes the framework will happily
let you make at runtime.

## 📋 Requirements

| | Supported |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 12.67+ and 13.26+ |
| PHPStan | 2.2.2+ |

Only the two most recent Laravel releases are supported, and each is required at a recent
minor version rather than the oldest release of that major. This keeps the codebase free of
version-shim workarounds — `composer update` will pull a supported minor for you.

## 🚀 Installation

Install as a development dependency with [Composer][composer]:

```bash
composer require --dev calebdw/phpstan-laravel
```

If you use the [PHPStan extension installer][extension-installer] you are done. Otherwise
include the extension in your `phpstan.neon` (or `phpstan.neon.dist`):

```neon
includes:
    - vendor/calebdw/phpstan-laravel/extension.neon
```

Then analyse as usual:

```bash
vendor/bin/phpstan analyse
```

If your project uses squashed schema dumps (`database/schema`), also install an
SQL parser — neither is a hard requirement, so you pick the one whose license
you want in your dependency tree:

```bash
composer require --dev iamcal/sql-parser      # MIT
composer require --dev phpmyadmin/sql-parser  # GPL-2.0-or-later
```

Either works; see [`sqlParser`](docs/custom-config-parameters.md#sqlparser) to
select one explicitly or register your own.

> [!NOTE]
> A GPL-2.0 package in `require-dev` does **not** put your application under the
> GPL. Copyleft applies to distributing the code, and a dev-only analyser is not
> linked into or shipped with what you deploy — `composer install --no-dev`
> excludes it outright. The MIT option is there for projects with a blanket
> policy against GPL code, which is a policy question rather than a legal one.
> See [the full note](docs/custom-config-parameters.md#a-note-on-the-gpl-20-parser).

> [!TIP]
> Coming from Larastan? See the [migration guide](docs/migrating-from-larastan.md).

## ⚙️ Configuration

Every option provided by this extension lives under the `laravel:` key, keeping it clearly
separated from PHPStan's own parameters:

```neon
parameters:
    level: 6
    paths:
        - app

    laravel:
        checkModelProperties: true
        checkUnusedViews: true
        viewDirectories:
            - resources/views
```

Options are schema validated, so a typo or a misplaced key fails fast with a clear error
instead of being silently ignored.

See [custom config parameters](docs/custom-config-parameters.md) for the full reference,
including migration and schema scanning, multiple database connections, and directory
overrides.

## ✨ What you get

**Eloquent that actually type checks.** Model properties are resolved from your migrations
and schema dumps, so `$user->emial` is an error rather than a mystery `mixed`. Relations,
custom builders, model factories, custom collections, and `$appends` are all understood.
See [features](docs/features.md).

**Sane return types across the framework.** Facades, helpers, the container, HTTP client,
and console commands return what they really return — including array shapes for `config()`
based on your own config files.

**Laravel-aware custom types.** `view-string` verifies a Blade view exists, and
`model-property<Model>` verifies a column exists. Both are applied throughout the core stubs,
so they work without any annotations of your own. See [custom types](docs/custom-types.md).

**Rules for Laravel-specific mistakes.** Detailed below.

Some parts of the framework remain genuinely too dynamic to analyse. Those cases, and how to
silence them, are documented in [errors to ignore](docs/errors-to-ignore.md).

## 📏 Rules

These rules are always active:

| Rule | Catches |
| --- | --- |
| `RelationExistenceRule` | References to relations that do not exist |
| `CheckDispatchArgumentTypesCompatibleWithClassConstructorRule` | `dispatch()` calls whose arguments do not match the job or event constructor |
| `DeferrableServiceProviderMissingProvidesRule` | Deferrable providers that forget to implement `provides()` |
| `UndefinedArgumentOrOptionRule` | Console commands reading arguments or options they never defined |
| `NoUselessValueFunctionCallsRule` | Pointless `value()` calls |
| `NoUselessWithFunctionCallsRule` | Pointless `with()` calls |

These are toggled with a boolean option under `laravel:`:

| Rule | Option | Default |
| --- | --- | --- |
| `NoModelMake` | `noModelMake` | ✅ on |
| `NoUnnecessaryCollectionCall` | `noUnnecessaryCollectionCall` | ✅ on |
| `ModelAppendsRule` | `checkModelAppends` | ✅ on |
| `ConfigAccessorRule` | `checkConfigAccessors` | ✅ on |
| `NoEnvCallsOutsideOfConfig` | `noEnvCallsOutsideOfConfig` | ✅ on |
| `ModelPropertyRule` | `checkModelProperties` | ❌ off |
| `OctaneCompatibilityRule` | `checkOctaneCompatibility` | ❌ off |
| `UnusedViewsRule` | `checkUnusedViews` | ❌ off |
| `NoMissingTranslationsRule` | `checkMissingTranslations` | ❌ off |
| `NoPublicModelScopeAndAccessorRule` | `checkModelMethodVisibility` | ❌ off |
| `NoAuthFacadeInRequestScopeRule` / `NoAuthHelperInRequestScopeRule` | `checkAuthCallsWhenInRequestScope` | ❌ off |
| `NoUnnecessaryEnumerableToArrayCalls` | `noUnnecessaryEnumerableToArrayCalls` | ❌ off |
| `NoModelForwardingToBuilder` | `noModelForwardingToBuilder` | ❌ off |
| `NoModelStaticForwardingToBuilder` | `noModelStaticForwardingToBuilder` | ❌ off |

Each rule is documented with examples in [rules](docs/rules.md).

Every error carries a `laravel.` prefixed [identifier][identifiers], so you can ignore a
whole class of error precisely:

```neon
parameters:
    ignoreErrors:
        - identifier: laravel.noModelMake
```

## 📚 Documentation

- [Features](docs/features.md) — what the extension understands about your application
- [Rules](docs/rules.md) — every rule, with examples and configuration
- [Custom config parameters](docs/custom-config-parameters.md) — the full option reference
- [Custom types](docs/custom-types.md) — `view-string` and `model-property`
- [Errors to ignore](docs/errors-to-ignore.md) — known limits and how to handle them
- [Migrating from Larastan](docs/migrating-from-larastan.md)

## 👊 Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) to get started.

## 🙏 Credits

This package began as a fork of [larastan/larastan][larastan], created by
[Can Vural][can] and [Nuno Maduro][nuno] and improved by many contributors over the years.
It would not exist without their work.

## 📄 License

Open-sourced software licensed under the [MIT license](LICENSE).

<!-- links -->
[phpstan]: https://phpstan.org
[composer]: https://getcomposer.org
[extension-installer]: https://phpstan.org/user-guide/extension-library#installing-extensions
[identifiers]: https://phpstan.org/user-guide/ignoring-errors#ignoring-by-identifier
[larastan]: https://github.com/larastan/larastan
[can]: https://github.com/canvural
[nuno]: https://github.com/nunomaduro
