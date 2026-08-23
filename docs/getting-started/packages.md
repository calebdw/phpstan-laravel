# Analysing a package

Most of what this extension knows comes from booting your application: booting
registers every service provider, which is how the container, the config
repository and the view finder come to hold real values. A package has no
application to boot, so that has to be supplied.

## How the application is found

At startup the extension looks for an application in this order:

1. `bootstrap/app.php` in the current working directory, which is the normal
   case when you run PHPStan from an application root.
2. `bootstrap/app.php` three directories up, which covers being installed into
   an application's `vendor` directory.
3. Failing both, a [Testbench][testbench] application, if Testbench is
   installed.

So for a package the requirement is simply:

```bash
composer require --dev orchestra/testbench
```

Testbench is a development dependency of your package, not of anything that
installs it.

!!! warning "Without it, inference degrades quietly"

    If no application is found and Testbench is not installed, analysis still
    runs. Nothing errors, but every lookup that would have gone to the
    container returns nothing, so `config()` calls, `app()` and `resolve()`
    calls, view names and model metadata all fall back to their widest types.
    The result looks like the extension is doing very little rather than like
    something is wrong. If a package's analysis seems strangely uninformative,
    check that Testbench is installed first.

## Registering your providers

The Testbench application is created with package discovery enabled, so a
provider declared in your `composer.json` is registered the same way it would
be in a real application:

```json
{
    "extra": {
        "laravel": {
            "providers": [
                "Acme\\Billing\\BillingServiceProvider"
            ]
        }
    }
}
```

For anything discovery cannot infer, add a `testbench.yaml` at your package
root. It is read if present, and Testbench's own documentation covers the full
schema:

```yaml
providers:
  - Acme\Billing\BillingServiceProvider

env:
  DB_CONNECTION: testing
```

If a provider fails to boot, the failure is reported with the underlying
exception rather than as a wall of unrelated errors, because nothing useful can
be analysed once booting has failed.

## Configuration types

This is the one place where a package genuinely behaves differently. In an
application, `config()` calls are answered from the booted container, which
holds the merged configuration of the app and every package. In a package
nothing publishes or merges your own config files, so the repository knows
nothing about them.

Rather than making you stand up a full Testbench workbench just to get config
types, point [`configDirectories`](../reference/configuration.md#configdirectories)
at your config files and they are parsed statically instead:

```neon
parameters:
    laravel:
        configDirectories:
            - config
```

```php
// config/billing.php
return ['default' => 'stripe', 'currency' => 'usd'];

// src/Billing.php
config('billing.default'); // string, not mixed
```

The container is always asked first, so this can only fill in keys the
container does not have. It never changes the types an application gets.

## Columns

A package usually has no migrations of its own, and the Testbench application
has no database. If your package ships migrations and you want model properties
resolved from them, point
[`migrationDirectories`](../reference/configuration.md#migrationdirectories) at
them:

```neon
parameters:
    laravel:
        migrationDirectories:
            - database/migrations
```

If your package defines models against tables owned by the host application,
there is nothing to scan and no way for the extension to know those columns.
Annotate those models with `@property` and leave
[`checkModelProperties`](../reference/configuration.md#checkmodelproperties)
off, since every column would otherwise be reported as missing.

<!-- links -->
[testbench]: https://packages.tools/testbench
