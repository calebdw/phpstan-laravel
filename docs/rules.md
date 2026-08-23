# Rules

Every rule this extension provides is listed here with its error identifier,
the option that switches it on or off, and what it reports.

Prefer ignoring errors [by identifier][identifiers] over ignoring by message:
identifiers are covered by the [backward compatibility
policy](backward-compatibility.md), message wording is not.

```neon
parameters:
    ignoreErrors:
        - identifier: laravel.modelMake
```

Toggles live under `laravel.rules`, so a rule named `modelMake` here is
`laravel.rules.modelMake` in your configuration:

```neon
parameters:
    laravel:
        rules:
            modelMake: false
```

## Model make

**identifier**: `laravel.modelMake` — **option**: `rules.modelMake`, default `true`

Checks for calls to the static method `make()` on subclasses of `Illuminate\Database\Eloquent\Model`.
While its usage does not result in an error, unnecessary work is performed and the
model is needlessly instantiated twice. Simply using `new` is more efficient.

### Examples

```php
User::make()
```

Will result in the following error:

```
Called 'Model::make()' which performs unnecessary work, use 'new Model()'.
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            modelMake: false
```

## Unnecessary collection call

**identifier**: `laravel.unnecessaryCollectionCall` — **option**: `rules.unnecessaryCollectionCall.enabled`, default `true`

Checks for method calls on instances of `Illuminate\Support\Collection` and their 
subclasses. If the same result could have been determined 
directly with a query then this rule will produce an error.
This rule exists to reduce unnecessarily heavy queries on the database 
and to prevent unneeded loops over Collections.

### Examples

```php
User::all()->count();
$user->roles()->pluck('name')->contains('a role name');
```

Will result in the following errors:
```
Called 'count' on Laravel collection, but could have been retrieved as a query.
Called 'contains' on Laravel collection, but could have been retrieved as a query.
```

To fix the errors, the code in the previous example could be changed to:
```php
User::count();
$user->roles()->where('name', 'a role name')->exists();
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            unnecessaryCollectionCall:
                enabled: false
```

Every collection method is checked by default. `only` narrows that to a
specific set:

```neon
parameters:
    laravel:
        rules:
            unnecessaryCollectionCall:
                only: ['count', 'first']
```

`except` is the inverse, leaving the listed methods alone:

```neon
parameters:
    laravel:
        rules:
            unnecessaryCollectionCall:
                except: ['contains']
```

## Unnecessary enumerable toArray call

**identifier**: `laravel.unnecessaryEnumerableToArrayCall` — **option**: `rules.unnecessaryEnumerableToArrayCall`, default `true`

Catches `toArray()` calls on an `Enumerable` whose values cannot be
`Arrayable`. `toArray()` recursively converts any `Arrayable` items it finds,
so on a collection that cannot contain one it does strictly more work than
`all()` for an identical result.

### Examples

```php
collect([1, 2, 3])->toArray();
```

Will result in the following error:

```
Called [toArray()] on an Enumerable which does not contain any Arrayables.
```

Use `all()` instead:

```php
collect([1, 2, 3])->all();
```

The rule fires only when the value type is known *not* to be `Arrayable`. A
collection of models, or one whose value type cannot be resolved, is left
alone, so it will not flag a `toArray()` that is doing real work.

### Configuration

```neon
parameters:
    laravel:
        rules:
            unnecessaryEnumerableToArrayCall: false
```

## Model properties

**identifier**: PHPStan's own, e.g. `argument.type` — **option**: [`checkModelProperties`](custom-config-parameters.md#checkmodelproperties), default `false`

This one is not a rule. Enabling the option activates the
[`model-property`](custom-types.md) type, and the mismatches are then reported
by PHPStan's ordinary argument checks — which is why the errors carry core
identifiers rather than a `laravel.*` one.

Every argument typed `model-property` is checked against the model's columns,
and an argument naming a column the model does not have is reported.

### Configuration

```neon
parameters:
    laravel:
        checkModelProperties: true
```

Whether it is accurate depends on how completely your columns were resolved.
Where migrations or schema dumps are missing, or a table is built in a way the
scanner cannot follow, the gap surfaces as a false positive rather than as
silence, which is why it is off by default. Point
[`migrationDirectories`](custom-config-parameters.md#migrationdirectories) and
[`schemaDirectories`](custom-config-parameters.md#schemadirectories) at the
right places before enabling it.

### Basic example

```php
User::create([
    'name' => 'John Doe',
    'emaiil' => 'john@example.test'
]);
```

Here we have a typo in `email` column. So if we run analysis on this file this extension will generate the following error:

```
Property 'emaiil' does not exist in App\User model.
```

This check will be done automatically on Laravel's core methods where a property is expected. But you can also typehint the `model-property` in your own code to take advantage of this analysis.

You can define a function like this:
```php
/**
 * @phpstan-param model-property<\App\User> $property
 */
function takesOnlyUserModelProperties(string $property)
{
    // ...
}
```

And if you call the function above with a property that does not exist in User model, this extension will warn you about it.

```php
// Property 'emaiil' does not exist in App\User model.
takesOnlyUserModelProperties('emaiil');
```

## Octane compatibility

**identifier**: `laravel.octaneCompatibility` — **option**: `rules.octaneCompatibility`, default `false`

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

## Relation existence

**identifier**: `laravel.relationExistence` — always enabled

Checks that the relations passed to the Eloquent builder methods below exist.
Nested relations are supported.

Supported Eloquent builder methods are:
- `has`
- `orHas`
- `doesntHave`
- `orDoesntHave`
- `whereHas`
- `withWhereHas`
- `orWhereHas`
- `whereDoesntHave`
- `orWhereDoesntHave`

### Examples

For the following code:
```php
\App\User::query()->has('foo');
\App\Post::query()->has('users.transactions.foo');
```

This extension will report two errors:
```
Relation 'foo' is not found in App\User model.
Relation 'foo' is not found in App\Transaction model.
```
## Dispatch argument types

**identifiers**: `laravel.jobs.noConstructor`, `laravel.events.noConstructor`, and PHPStan's own for the argument errors — always enabled

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
carry its identifiers rather than `laravel.*` ones. Ignoring them by identifier
therefore cannot be narrowed to job dispatches.

## Useless value() call

**identifier**: `laravel.uselessConstructs.value` — always enabled

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

**identifier**: `laravel.uselessConstructs.with` — always enabled

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

## Deferrable service provider without provides()

**identifier**: `laravel.deferrableServiceProvider.missingProvides` — always enabled

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

## Unused view

**identifier**: `laravel.unusedView` — **option**: `rules.unusedView`, default `false`

Finds views in your application that are never used.

> **NOTE**: Due to the nature of static analysis, this rule can produce false positives. It cannot find every usage of a view, so it is possible that a view is reported as unused when it is actually used. This is why it's an optional rule.

### Configuration

```neon
parameters:
    laravel:
        rules:
            unusedView: true
```

Blade files under `resources/views` are scanned by default. Use
[`viewDirectories`](custom-config-parameters.md#viewdirectories) for views kept
elsewhere:

```neon
parameters:
    laravel:
        rules:
            unusedView: true
        viewDirectories:
            - domainA/resources/views
            - a/path/to/views
```

### Supported View Usages

- `view` helper function.
- `$this->markdown` and `$this->view` methods in Mailables.
- `Illuminate\View\Factory::make` method.
- `Illuminate\Support\Facades\View::make` method.
- `Illuminate\Support\Facades\Route::view` method.
- `@extends` Blade directive.
- `@include` Blade directive.
- `@includeIf` Blade directive.
- `@includeUnless` Blade directive.
- `@includeWhen` Blade directive.
- `@includeFirst` Blade directive.

## Missing translation

**identifier**: `laravel.missingTranslation` — **option**: `rules.missingTranslation`, default `false`

Finds untranslated strings in your application. It is primarily meant for applications that make use of the dot syntax like `messages.greet`. If you're using translation strings as keys, this rule may be unnecessary. Enabling this rule may decrease performance as it will scan the available views and translations.

Translations from vendors like `vendor::key` will not be checked.

> **NOTE**: If you store your translations in a database, this rule will not be able to detect them. You should leave this rule disabled in such cases.

### Examples

For the following code:
```php
__('messages.greet')
```

This extension may report the following error:
```
Translation "messages.greet" has not been found.
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            missingTranslation: true
```

`resources/lang` is scanned by default. If your translations live elsewhere,
register every path with
[`translationDirectories`](custom-config-parameters.md#translationdirectories):

```neon
parameters:
    laravel:
        rules:
            missingTranslation: true
        translationDirectories:
            - resources/lang
            - resources/translations
```

## Env call outside config

**identifier**: `laravel.envCallOutsideConfig` — **option**: `rules.envCallOutsideConfig`, default `true`

Checks for `env()` calls outside the `config` directory, which return `null`
once the config is cached.

### Examples

Suppose this calls happens somewhere in your code outside the `config` directory:

```php
env('APP_ENV')
```

It will result in the following error:

```
Called 'env' outside of the config directory which returns null when the config is cached, use 'config'.")
```

Use the corresponding configuration option instead:

```php
config('app.env')
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            envCallOutsideConfig: false
```

The application's own config directory is where `env()` is allowed by default.
If your configuration files live elsewhere, name those directories with
[`configDirectories`](custom-config-parameters.md#configdirectories):

```neon
parameters:
    laravel:
        configDirectories:
            - src/config
            - tests
```

## Config accessor

**identifier**: `laravel.configAccessor` — **option**: `rules.configAccessor`, default `true`

Checks the config repository's typed accessors — `string`, `integer`, `float`,
`boolean`, `array` and `collection` — against the type of the key being read.
None of them coerce: each throws an `InvalidArgumentException` when the value
is not already of the required type, and `collection()` delegates to `array()`.
Reading a key of the wrong type is therefore a guaranteed runtime failure
rather than a style issue.

### Examples

```php
// config/auth.php
return [
    'defaults' => [
        'guard' => 'web',
    ],
    'password_timeout' => 10800,
];
```

```php
Config::string('auth.defaults.guard'); // fine, the value is a string
Config::array('auth.defaults');        // fine, the value is an array
Config::array('auth.defaults.guard');  // always throws
```

The last call reports:

```
Config key 'auth.defaults.guard' is string, but 'array' requires an array.
```

Because the checks are strict, an integer is not accepted where a float is
required:

```php
Config::float('auth.password_timeout'); // always throws, the value is an int
```

Both call styles are covered, so `Config::string(...)`, `config()->string(...)`
and a repository injected as either the concrete class or the contract are all
checked.

A key is only reported when its type is genuinely known — resolved from the
booted container, or from
[`configDirectories`](custom-config-parameters.md#configdirectories) for keys
the container cannot answer. Anything neither can resolve could hold any type,
so it is left alone; an unrecognised key cannot produce a false positive.

Passing a default does not suppress the error, because a default only applies
when the key is *absent*. A key that exists holding the wrong type is returned
and rejected either way:

```php
Config::string('auth.password_timeout', 'fallback'); // still throws
```

One case is deliberately not reported: a key explicitly set to `null` is
indistinguishable from a missing key once resolved, so it is skipped even
though it would throw.

Values are read from the environment the analysis runs in. A key whose value
comes from `env()` is judged by the local environment, so a value whose type
differs between environments is checked against the local one.

### Configuration

```neon
parameters:
    laravel:
        rules:
            configAccessor: false
```

## Model appends

**identifier**: `laravel.modelAppends` — **option**: `rules.modelAppends`, default `true`

Checks the model's `$appends` property for computed properties. The properties added to `$appends` array should both exist in the model and be computed properties.

### Examples

```php
class User extends \Illuminate\Database\Eloquent\Model
{
    protected $appends = ['email'];
}
```

Now if you were to call `toArray` or `toJson` methods on an instance of User class, you'd expect to see the `email` there. But in reality it'd be `null` This rule prevents you from that mistake. So you'd get the following error:

```
Property 'email' is not a computed property, remove from $appends.
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            modelAppends: false
```

## Model method visibility

**identifiers**: `laravel.modelMethodVisibility.scope`, `laravel.modelMethodVisibility.accessor` — **option**: `rules.modelMethodVisibility`, default `false`

Ensures Eloquent model local query scopes and attribute accessors are not part of the public API. 
Local scopes and attribute accessors should be declared `protected`.

### Examples

Public local scope method:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // ❌ Should be protected
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
```

Will result in the following error:

```
Local query scope method 'scopeActive' should be declared as protected.
```

Public accessor returning `Attribute`:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // ❌ Should be protected
    public function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['first_name'].' '.$attributes['last_name'],
        );
    }
}
```

Will result in the following error:

```
Model accessor method 'fullName' should be declared as protected.
```

Fix by changing the visibility to `protected` in both cases.

### Configuration

```neon
parameters:
    laravel:
        rules:
            modelMethodVisibility: true
```

## Auth in request scope

**identifiers**: `laravel.authInRequestScope.facade`, `laravel.authInRequestScope.helper` — **option**: `rules.authInRequestScope`, default `false`

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

## Model forwarding to builder

**identifier**: `laravel.modelForwardingToBuilder` — **option**: `rules.modelForwardingToBuilder`, default `false`

Checks for calling methods on an `Illuminate\Database\Eloquent\Model` instance that are actually forwarded to a Builder instance.
It helps prevent unexpected behaviors like executing `first()`, `get()` on already fetched models.

### Examples

The following code:

```php
$post = Post::find(1);
$post->first();
```

Will result in the following error:

```
Method [first] is forwarded to a Builder instance, which is not allowed.
    💡 Use [::first()], [::query()->first()] or [->newQuery()->first()] instead.
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            modelForwardingToBuilder: true
```

## Model static forwarding to builder

**identifier**: `laravel.modelStaticForwardingToBuilder` — **option**: `rules.modelStaticForwardingToBuilder`, default `false`

Checks for calling methods on an `Illuminate\Database\Eloquent\Model` instance that are actually forwarded to a Builder instance.
It helps prevent hidden coupling and unexpected behaviors by ensuring you explicitly use `::query()` when calling query builder methods on a model.

### Examples

The following code:

```php
Post::first();
```

Will result in the following error:

```
Static method [first] is forwarded to a Builder instance, which is not allowed.
    💡 Use [::query()->first()] instead.
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            modelStaticForwardingToBuilder: true
```

## Undefined console argument or option

**identifiers**: `laravel.console.undefinedArgument`, `laravel.console.undefinedOption` — always enabled

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
