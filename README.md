<h1 align="center">phpstan-laravel</h1>

<p align="center">
  <strong>Static analysis that understands Laravel.</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://img.shields.io/packagist/v/calebdw/phpstan-laravel.svg" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://img.shields.io/packagist/dt/calebdw/phpstan-laravel.svg" alt="Total Downloads"></a>
  <a href="https://github.com/calebdw/phpstan-laravel/actions/workflows/tests.yml"><img src="https://github.com/calebdw/phpstan-laravel/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://badge.laravel.cloud/badge/calebdw/phpstan-laravel" alt="Laravel Compatibility"></a>
  <a href="https://github.com/laravel/boost"><img src="https://badge.laravel.cloud/boost-badge.svg" alt="Laravel Boost"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/calebdw/phpstan-laravel" alt="License"></a>
</p>

<p align="center">
  <a href="https://phpstan-laravel.dev"><strong>phpstan-laravel.dev</strong></a>
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

It also ships a set of Laravel-specific rules for mistakes the framework will
happily let you make at runtime.

## Install

```bash
composer require --dev calebdw/phpstan-laravel
```

With the [PHPStan extension installer][extension-installer] that is the whole
setup.

> [!TIP]
> Coming from Larastan? There is a migration guide in the
> [documentation][docs]. Do not install both: this package does not declare
> `replace` for `larastan/larastan`, so with both installed every error is
> reported twice.

## Documentation

Everything is at **[phpstan-laravel.dev][docs]**: installation and
configuration, what the extension understands about your code, every rule with
its examples and defaults, every option and error identifier, how to analyse a
package, and why you might switch from Larastan.

## Contributing

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md) to get
started.

## Acknowledgments

This package began as a fork of [larastan/larastan][larastan], created by
[Can Vural][can] and [Nuno Maduro][nuno] and improved by many contributors over
the years. It would not exist without their work.

## License

Open-sourced software licensed under the [MIT license](LICENSE).

<!-- links -->
[phpstan]: https://phpstan.org
[extension-installer]: https://phpstan.org/user-guide/extension-library#installing-extensions
[larastan]: https://github.com/larastan/larastan
[can]: https://github.com/canvural
[nuno]: https://github.com/nunomaduro
[docs]: https://phpstan-laravel.dev
