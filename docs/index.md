# phpstan-laravel

<p class="hero-tagline" markdown>
**Teaching PHPStan about Laravel's magic.**
</p>

Laravel leans on magic: facades, container bindings, dynamic Eloquent
properties, method forwarding, macros. A static analyser sees almost none of it
on its own, so the parts of your application that carry the most behaviour are
the parts it checks least.

This extension closes that gap. It boots your application during analysis and
combines that with stubs, reflection extensions and schema scanning, so PHPStan
can reason about your models, relations, collections, configuration and views:

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

Every type above is what the analyser actually reports, not an aspiration.

<div class="grid cards" markdown>

- :material-table-column:{ .lg .middle } **Eloquent that type checks**

    ---

    Columns come from your migrations and schema dumps, per connection. Casts,
    appends, dates and traits are read from a real model instance, so anything
    a trait contributes is visible.

    [:octicons-arrow-right-24: Model properties](guide/model-properties.md)

- :material-cog:{ .lg .middle } **Typed configuration**

    ---

    `config()` returns array shapes built from your own config files, and the
    typed accessors are checked against them.

    [:octicons-arrow-right-24: Configuration types](guide/config-types.md)

- :material-ruler-square:{ .lg .middle } **Laravel-specific rules**

    ---

    Rules for mistakes the framework will happily let you make at runtime,
    each behind its own error identifier.

    [:octicons-arrow-right-24: Rules](rules/index.md)

- :material-tag-text:{ .lg .middle } **Laravel-aware types**

    ---

    `view-string` verifies a Blade view exists and `model-property<Model>`
    verifies a column exists, both applied throughout the core stubs.

    [:octicons-arrow-right-24: Custom types](guide/custom-types.md)

</div>

## Install

```bash
composer require --dev calebdw/phpstan-laravel
```

With the [PHPStan extension installer][extension-installer] that is the whole
setup. See [installation](getting-started/installation.md) for the manual
include and for projects that use squashed schema dumps.

## Where to go next

<div class="grid cards" markdown>

- **New here**

    ---

    [Installation](getting-started/installation.md), then
    [configuration](getting-started/configuration.md). Start at level 0 and
    work up; most of what this extension knows needs no configuration at all.

- **Coming from Larastan**

    ---

    [The migration guide](migrating-from-larastan.md) covers the mechanics, and
    [differences from Larastan](about/differences-from-larastan.md) covers why
    you might bother.

- **Chasing an error**

    ---

    Every error carries an identifier. Look it up in
    [the identifier reference](reference/identifiers.md), or read
    [troubleshooting](about/troubleshooting.md) for the known limits.

- **Analysing a package**

    ---

    A package has no application to boot. [Analysing a
    package](getting-started/packages.md) covers what changes.

</div>

<!-- links -->
[extension-installer]: https://phpstan.org/user-guide/extension-library#installing-extensions
