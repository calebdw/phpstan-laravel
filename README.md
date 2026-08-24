<p align="center">
  <img src="art/phpstan-laravel.webp" alt="phpstan-laravel" width="320">
</p>

<p align="center">
  <strong>Teaches PHPStan about Laravel's magic</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://img.shields.io/packagist/v/calebdw/phpstan-laravel.svg" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://badge.laravel.cloud/badge/calebdw/phpstan-laravel" alt="Laravel Compatibility"></a>
  <a href="https://github.com/laravel/boost"><img src="https://badge.laravel.cloud/boost-badge.svg" alt="Laravel Boost"></a>
  <a href="https://packagist.org/packages/calebdw/phpstan-laravel"><img src="https://img.shields.io/packagist/dt/calebdw/phpstan-laravel.svg" alt="Total Downloads"></a>
  <a href="https://github.com/calebdw/phpstan-laravel/actions/workflows/tests.yml"><img src="https://github.com/calebdw/phpstan-laravel/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/calebdw/phpstan-laravel" alt="License"></a>
</p>

<p align="center">
  <a href="https://phpstan-laravel.dev"><strong>phpstan-laravel.dev</strong></a>
</p>

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

None of that comes from annotations you write. Columns are read from your
migrations and schema dumps, per connection. Relation types come from the return
types you already declare. Config shapes come from your own config files. Casts,
appends and dates are read from a real model instance, so whatever a trait
contributes is understood too, including traits from packages you did not write.

On top of the inference it ships rules for the mistakes Laravel will happily let
you make at runtime: dispatching a job with arguments its constructor will not
accept, reading a console option the command never defined, `Model::make()`,
`env()` outside your config, a deferrable provider missing `provides()`, a
`$appends` entry that resolves to nothing. Each has its own error identifier, so
you can adopt them one at a time.

It works at every PHPStan level, on applications and on packages.

Everything else is at **[phpstan-laravel.dev][docs]**: installation,
configuration, every rule, every option and identifier, and the guide for moving
over from Larastan.

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
[larastan]: https://github.com/larastan/larastan
[can]: https://github.com/canvural
[nuno]: https://github.com/nunomaduro
[docs]: https://phpstan-laravel.dev
