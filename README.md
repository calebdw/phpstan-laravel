<h1 align="center">phpstan-laravel</h1>

<p align="center">
  <strong>Static analysis that understands Laravel.</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://img.shields.io/packagist/v/calebdw/phpstan-laravel.svg" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://img.shields.io/packagist/dt/calebdw/phpstan-laravel.svg" alt="Total Downloads"></a>
  <a href="https://github.com/calebdw/phpstan-laravel/actions/workflows/tests.yml"><img src="https://github.com/calebdw/phpstan-laravel/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://badge.laravel.cloud/badge/calebdw/phpstan-laravel" alt="Laravel Compatibility"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/calebdw/phpstan-laravel" alt="License"></a>
</p>

<p align="center">
  <a href="https://calebdw.github.io/phpstan-laravel/"><strong>Documentation</strong></a>
</p>

------

Laravel leans on magic: facades, container bindings, dynamic Eloquent
properties, method forwarding, macros. A static analyser sees almost none of it
on its own, so the parts of your application that carry the most behaviour are
the parts it checks least.

This [PHPStan][phpstan] extension closes that gap. It boots your application
during analysis and combines that with stubs, reflection extensions and schema
scanning, so PHPStan can reason about your models, relations, collections,
configuration and views.

```php
$user = User::query()->firstOrFail();   // App\User

$user->email;                           // string
$user->emial;                           // Access to an undefined property
$user->accounts;                        // App\AccountCollection<int, App\Account>

User::query()->pluck('name');           // Collection<int, string>
$user->accounts()->pluck('name');       // Collection<int, string>
User::all()->groupBy('email');          // Collection<string, Collection<int, App\User>>

config('auth.defaults.guard');          // string|null
Config::string('auth.defaults.guard');  // string

User::create(['emial' => '...']);       // Property 'emial' does not exist
```

It also ships nineteen Laravel-specific rules for mistakes the framework will
happily let you make at runtime.

## Install

```bash
composer require --dev calebdw/phpstan-laravel
```

With the [PHPStan extension installer][extension-installer] that is the whole
setup. Otherwise include the extension in your `phpstan.neon`:

```neon
includes:
    - vendor/calebdw/phpstan-laravel/extension.neon
```

Then analyse as usual:

```bash
vendor/bin/phpstan analyse
```

Requires PHP 8.3+, Laravel 12.67+ or 13.26+, and PHPStan 2.2.2+. See
[installation][docs-install] for squashed schema dumps, and [analysing a
package][docs-packages] if there is no application to boot.

> [!TIP]
> Coming from Larastan? See the [migration guide][docs-migrate]. Do not install
> both: this package does not declare `replace` for `larastan/larastan`, so with
> both installed every error is reported twice.

## Documentation

Everything lives at **[calebdw.github.io/phpstan-laravel][docs]**:

- **[Getting started][docs-install]** for installation and configuration
- **[Guide][docs-guide]** for what the extension understands about your code
- **[Rules][docs-rules]** for all nineteen, with examples and defaults
- **[Reference][docs-reference]** for every option and every error identifier
- **[FAQ][docs-faq]** and **[troubleshooting][docs-trouble]** for the known limits
- **[Differences from Larastan][docs-diff]** for why you might switch

## Laravel Boost

If you use [Laravel Boost][boost], this package ships an AI guideline and a
`phpstan-laravel-analysis` skill covering how to run the analysis, how to read
an error identifier, the configuration surface, and what to do when a model
property is reported as missing. Boost picks both up automatically:

```bash
php artisan boost:update
```

## Contributing

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md) to get
started.

## Credits

This package began as a fork of [larastan/larastan][larastan], created by
[Can Vural][can] and [Nuno Maduro][nuno] and improved by many contributors over
the years. It would not exist without their work.

## License

Open-sourced software licensed under the [MIT license](LICENSE).

<!-- links -->
[phpstan]: https://phpstan.org
[extension-installer]: https://phpstan.org/user-guide/extension-library#installing-extensions
[boost]: https://github.com/laravel/boost
[larastan]: https://github.com/larastan/larastan
[can]: https://github.com/canvural
[nuno]: https://github.com/nunomaduro
[docs]: https://calebdw.github.io/phpstan-laravel/
[docs-install]: https://calebdw.github.io/phpstan-laravel/getting-started/installation/
[docs-packages]: https://calebdw.github.io/phpstan-laravel/getting-started/packages/
[docs-guide]: https://calebdw.github.io/phpstan-laravel/guide/model-properties/
[docs-rules]: https://calebdw.github.io/phpstan-laravel/rules/
[docs-reference]: https://calebdw.github.io/phpstan-laravel/reference/configuration/
[docs-faq]: https://calebdw.github.io/phpstan-laravel/about/faq/
[docs-trouble]: https://calebdw.github.io/phpstan-laravel/about/troubleshooting/
[docs-diff]: https://calebdw.github.io/phpstan-laravel/about/differences-from-larastan/
[docs-migrate]: https://calebdw.github.io/phpstan-laravel/migrating-from-larastan/
