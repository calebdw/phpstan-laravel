# Framework rules

Everything else: dispatching, service providers, console commands, Octane, the
auth helpers, and the two helper functions that are easy to call pointlessly.

## Dispatch argument types

`laravel.jobs.noConstructor`, `laravel.events.noConstructor` &middot; always enabled

Checks that the arguments you dispatch a job or event with are compatible with
its constructor.

### Examples

Assume the following job:

```php
final class ExampleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $foo,
        protected string $bar,
    ) {}

    // Rest of the job class
}
```

Dispatching the job with the following examples:

```php
ExampleJob::dispatch(1);
ExampleJob::dispatch('bar', 1);
```

will result in the following errors:

```
Job class ExampleJob constructor invoked with 1 parameter in ExampleJob::dispatch(), 2 required.
Parameter #1 $foo of job class ExampleJob constructor expects int in ExampleJob::dispatch(), string given.
Parameter #2 $bar of job class ExampleJob constructor expects string in ExampleJob::dispatch(), int given.
```

Dispatching a class that has no constructor with arguments anyway is reported
under `laravel.jobs.noConstructor`, or `laravel.events.noConstructor` for an
event:

```
Job class ExampleJob does not have a constructor and must be dispatched without any parameters.
```

The argument errors are produced by PHPStan's own argument checking, so they
carry its identifiers rather than `laravel.*` ones: `arguments.count` when the
number is wrong and `argument.type` when a type is. Ignoring either by
identifier therefore cannot be narrowed to job dispatches.

## Undefined console argument or option

`laravel.console.undefinedArgument`, `laravel.console.undefinedOption` &middot; always enabled

Checks `$this->argument()` and `$this->option()` calls inside an
`Illuminate\Console\Command` against the signature that command is registered
with, so a name that was never defined is caught rather than returning `null`
at runtime.

### Examples

```php
class SendReport extends Command
{
    protected $signature = 'report:send {user} {--queue}';

    public function handle(): void
    {
        $this->argument('users');
        $this->option('queued');
    }
}
```

Will result in the following errors:

```
Command "report:send" does not have argument "users".
Command "report:send" does not have option "queued".
```

Options are matched by shortcut as well as by name, and a command reachable
under more than one name is checked against each of them. Only literal string
arguments can be checked; a name built at runtime is left alone.

<!-- links -->
[identifiers]: https://phpstan.org/user-guide/ignoring-errors#ignoring-by-identifier

## Deferrable service provider without provides()

`laravel.deferrableServiceProvider.missingProvides` &middot; always enabled

Checks for a missing `provides()` method on a deferrable `ServiceProvider`.

### Examples

A correct `DeferrableProvider` returns an `array` of `string`s or `class-string`s in the 'provides' method:

```php
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class CorrectDeferrableProvider extends ServiceProvider implements DeferrableProvider
{
    public function register() {}
    
    public function provides(): array
    {
        return [
            'foo',
            'bar',
        ];
    }
}
```

When the method is not present, the ServiceProvider will not be used.

```php
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class IncorrectDeferrableProvider extends ServiceProvider implements DeferrableProvider
{
    public function register() {}
}
```

This will result in the following error:

```
ServiceProviders that implement the "DeferrableProvider" interface should implement the "provides" method that returns an array of strings or class-strings
```

## Octane compatibility

`laravel.octaneCompatibility` &middot; option `rules.octaneCompatibility` &middot; off by default

Checks your application for Laravel Octane compatibility. The reasoning is in
[the official Octane docs](https://laravel.com/docs/octane#dependency-injection-and-octane).

### Configuration

```neon
parameters:
    laravel:
        rules:
            octaneCompatibility: true
```

### Examples

Following code
```php
public function register()
{
    $this->app->singleton(Service::class, function ($app) {
        return new Service($app);
    });
}
```
Will result in the following error:

`Consider using bind method instead or pass a closure.`

## Auth in request scope

`laravel.authInRequestScope.facade`, `laravel.authInRequestScope.helper` &middot; option `rules.authInRequestScope` &middot; off by default

Warns you if you are using `Auth::check()`, `Auth::user()`, `Auth::guest()`, `auth()->check()`, `auth()->user()`, or `auth()->guest()` while you have access to the request already in your current scope with `$request` variable. So it should only warn if there is a variable named `$request` in the current scope with `Illuminate\Http\Request` type (or any child class).

### Examples

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MyController
{
    public function __invoke(Request $request)
    {
        if (Auth::check()) {
            //
        }
    }
}
```

Will result in the following error:

```
Do not use Auth::check() in a class that has access to the request. Use $request->user() !== null instead.
```

You can fix this by using the `$request` variable directly:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MyController
{
    public function __invoke(Request $request)
    {
        if ($request->user() !== null) {
            //
        }
    }
}
```

### Configuration

One option covers both, and the facade and helper forms report under separate
identifiers so you can ignore one without the other.

```neon
parameters:
    laravel:
        rules:
            authInRequestScope: true
```

## Useless value() call

`laravel.uselessConstructs.value` &middot; always enabled

Reports calls to `value()` that return the first argument unchanged.

### Examples

Calling the following functions:

```php
$foo = value('foo');
$bar = value(true);
```

will result in errors:

```
Calling the helper function 'value()' without a closure as the first argument simply returns the first argument without doing anything
Calling the helper function 'value()' without a closure as the first argument simply returns the first argument without doing anything
```

## Useless with() call

`laravel.uselessConstructs.with` &middot; always enabled

Reports calls to `with()` that return the value unchanged.

### Examples

Calling the following functions:

```php
$foo = with('foo');
$bar = with('bar', null);
```

will result in errors:

```
Calling the helper function 'with()' with only one argument simply returns the value itself. if you want to chain methods on a construct, use '(new ClassName())->foo()' instead
Calling the helper function 'with()' without a closure as the second argument simply returns the value without doing anything
```
