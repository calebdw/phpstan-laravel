# Cache return types

Laravel's `remember` family is typed with `@template TCacheValue` and a
`Closure(): TCacheValue` callback. That flows through the `Cache` facade
stub (which mixes in the repository) and the `cache()` helper.

```php
$user = Cache::remember(
    "user:$id",
    3600,
    fn () => User::findOrFail($id),
); // App\User

$users = cache()->rememberForever(
    'users',
    fn () => User::query()->get(),
); // Illuminate\Database\Eloquent\Collection<int, App\User>
```

Nullable and union callback returns are preserved:

```php
Cache::remember('key', 60, fn () => User::find($id)); // App\User|null

/** @param Closure(): (User|null) $callback */
function load(Closure $callback): void
{
    Cache::remember('key', 60, $callback); // App\User|null
}
```

`rememberWithWarmth()` is `array{TCacheValue, bool}`.

## Limitations

- `flexible()` lives on the concrete repository, not the contract; calls
  typed only as `Illuminate\Contracts\Cache\Repository` will not see that
  method.
