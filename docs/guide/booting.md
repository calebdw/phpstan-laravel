# Booting your application

Most of what this extension knows, it learns by booting your application.

That is a real trade, and worth understanding before it surprises you. A
static analyser that never runs anything is predictable but ignorant: it cannot
know what `app('cache')` returns, what shape `config('services.stripe')` has,
which views exist, or which macros are registered, because Laravel decides all
of that at runtime. Booting is what turns those from `mixed` into real types.

## What actually runs

Booting is the same sequence `php artisan` performs before it runs a command:

- every service provider's `register()` and then `boot()`
- package discovery, so providers from your dependencies are registered too
- the alias loader, config repository, view finder and container bindings

It does not stop at the providers, and that is the part worth knowing. Anything
a provider resolves from the container is **constructed**, and so is everything
that class asks for in its own constructor, and so on down. A provider that
resolves one service can run a dozen constructors it never names.

Console commands are constructed as well, all of them, along with whatever they
inject. This extension builds the console application so it can check
`$this->argument()` and `$this->option()` against the signature each command is
registered with, and building it instantiates every command:

```php
final class ImportCommand extends Command
{
    protected $signature = 'import:run {file}';

    // Runs during analysis, and so does Importer's constructor.
    public function __construct(private Importer $importer)
    {
        parent::__construct();
    }
}
```

## What does not

Controllers are **not** constructed. A route stores the class name and the
method to call, so registering one touches nothing. The same holds for jobs,
listeners, form requests and middleware: none are built until something
dispatches to them, and nothing dispatches during analysis.

No route is handled, no query is sent, nothing is queued. The database is never
contacted either, since columns come from reading migration files and schema
dumps as text, so analysis works with no database available.

A manager's driver is not built either, as long as its `create{Driver}Driver()`
method declares what it returns; the declared type is read instead. See
[managers](../about/differences-from-larastan.md#managers).

## What to watch out for

The consequence is that **anything your providers do on boot, they do on every
analysis run**. Usually that is nothing of consequence. Occasionally it is not.

Things worth keeping out of `register()` and `boot()`, out of a command's
constructor, and out of the constructor of anything either of those resolves:

- **Writing.** Creating files or directories, touching a cache, writing a log
  that ends up in your repository, running migrations.
- **Network calls.** Fetching remote config, warming a cache from an API,
  checking a license server. Every one of these is now on the critical path of
  your test suite and your editor.
- **Sending anything.** Mail, notifications, webhooks, analytics events.
- **Anything slow.** Boot happens once per run, so a provider that takes two
  seconds adds two seconds to every analysis.
- **Anything that assumes a request.** There is no request, no session and no
  authenticated user during analysis.

The constructor is the easy one to miss, because nothing in the provider or the
command mentions the class doing the work. It only has to be reachable through a
constructor argument:

```php
final class ReportBuilder
{
    public function __construct(private Filesystem $files)
    {
        // Runs during analysis too.
        $this->files->makeDirectory(storage_path('reports'));
    }
}
```

Doing the work lazily, when something actually asks for it, fixes this and is
usually better design anyway.

!!! note "Failures are fatal, and deliberately so"

    If a provider throws while booting, the run stops and reports that
    exception. Nothing useful can be analysed once the container is
    half-built, so a wall of unrelated errors would be worse than one accurate
    one. See [troubleshooting](../about/troubleshooting.md#the-run-fails-while-booting).

## Your local environment is the one that is read

Boot reads your `.env`, so config values are whatever they are on your machine
or on CI. A key whose value comes from `env()` is judged by that environment,
which is why [config types](config-types.md) widen scalars to their general
type rather than treating the local value as a literal.

It also means a provider guarded by `app()->environment('production')` does not
run during analysis, and code only reachable in production is analysed with
whatever the local bindings happen to be.

## When something really must not run

PHPStan defines the constant `__PHPSTAN_RUNNING__` before anything else
happens, so you can guard the rare piece of work that genuinely cannot happen
during analysis:

```php
public function boot(): void
{
    if (defined('__PHPSTAN_RUNNING__')) {
        return;
    }

    $this->warmCacheFromRemoteApi();
}
```

The constant comes from PHPStan itself rather than from this package, so the
guard costs you no dependency and keeps working if you swap the extension out.

Reach for it sparingly. If a provider needs the guard, that is usually a signal
the work belongs somewhere lazier than boot, and moving it there helps your
application start faster too. The guard is the right answer when the work is
genuinely unavoidable at boot and genuinely unsafe to repeat.
