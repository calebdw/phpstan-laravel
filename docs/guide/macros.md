# Macros

Laravel stores macros in static properties at runtime. This extension boots the
application before analysis and reads those properties, so macros registered by
a service provider are available without a stub or `@method` annotation:

```php
final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Collection::macro('active', function (): static {
            return $this->filter->active;
        });
    }
}
```

The closure supplies the parameters and return type. In this example PHPStan
understands `$collection->active()` as returning a `Collection`.

Registration has to run while the application boots. A macro registered inside
a test helper, controller action, queued job or conditional that is false during
analysis cannot be discovered. Add an `@method` annotation to the class in that
case; it documents the same API for PHPStan, editors and readers.

## Static and instance macros

The closure declaration determines the call form by default. A normal closure
defines an instance macro and can use the instance Laravel binds to `$this`:

```php
Collection::macro('active', function (): static {
    return $this->filter->active;
});

$users->active();
```

A `static` closure defines a static macro:

```php
Collection::macro('fromCsv', static function (string $csv): Collection {
    return collect(str_getcsv($csv));
});

Collection::fromCsv($csv);
```

Keeping that distinction lets PHPStan report a static call to an instance macro.
When `phpstan-strict-rules` is installed, it can report the inverse as well: an
instance call to a static macro.

## Static-facing classes

Some Laravel classes have a public API that is conventionally static even when
a macro was registered with a normal closure. Calls on these classes are allowed
by default:

- `Illuminate\Support\Arr`
- `Illuminate\Support\Str`
- `Illuminate\Support\Number`
- `Illuminate\Support\Benchmark`
- `Illuminate\Validation\Rule`

Only dynamically discovered macros receive this exception. A native instance
method on a listed class is still an error when called statically.

Facades are handled separately. Their entire purpose is to expose the instance
behind a facade accessor through static calls, so dynamically forwarded facade
methods and facade macros are always accepted. A native instance method declared
directly on the facade class is not.

## Configuring exceptions

[`staticMacroClasses`](../reference/configuration.md#staticmacroclasses) adds
classes whose macros may be called statically. Entries also apply to subclasses.
For example, a project that deliberately uses Eloquent's static forwarding for
builder macros can add every model:

```neon
parameters:
    laravel:
        staticMacroClasses:
            - Illuminate\Database\Eloquent\Model
```

Models are not included by default. Models have meaningful instances, builder
macros commonly use `$this`, and retaining the distinction catches a static call
where an instance call better expresses the code. Laravel permits the forwarding,
so projects that prefer it can opt in once rather than ignoring each call.

NEON merges lists from included configuration files. The example above appends
`Model` to the defaults. Add `!` to replace the defaults instead:

```neon
parameters:
    laravel:
        staticMacroClasses!:
            - App\Models\BaseModel
```

An empty replacement enables strict call-form checking for every non-facade
macro:

```neon
parameters:
    laravel:
        staticMacroClasses!: []
```
