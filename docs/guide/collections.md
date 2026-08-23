# Collections

## pluck and keyBy

`pluck` and `keyBy` resolve their column against the collection's value type,
including nested paths and callbacks:

```php
$users->pluck('name', 'id');       // Collection<int, string>
$users->keyBy('name');             // EloquentCollection<string, App\User>
$posts->pluck('user.name');        // Collection<int, string>
$users->keyBy(fn ($u) => $u->id);  // EloquentCollection<int, App\User>
```

`pluck` rewrites both halves, so plucking a column off a collection of models
gives a support collection of whatever that column holds. `keyBy` rewrites only
the keys, so the value type and the collection class carry over.

### On a builder or a relation

`pluck` also resolves on an Eloquent builder, and on a relation, which forwards
it to the builder underneath:

```php
User::query()->pluck('name');            // Collection<int, string>
$user->accounts()->pluck('name');        // Collection<int, string>
$user->accounts()->pluck('name', 'id');  // Collection<int, string>
$post->user()->pluck('name');            // Collection<int, string>
```

The column is read from the *related* model, so `$post->user()->pluck('name')`
resolves `name` on `User` rather than on `Post`. Builder methods in the middle
of the chain do not break it, since they return the relation.

## groupBy

`groupBy` nests one level per grouper, and an array argument means successive
levels rather than a nested path:

```php
$users->groupBy('name');
// Collection<string, Collection<int, App\User>>

$users->groupBy(['name', 'id']);
// Collection<string, Collection<int, Collection<int, App\User>>>
```

`preserveKeys` decides the innermost keys:

```php
/** @var Collection<string, App\User> $keyed */
$keyed->groupBy('id');       // Collection<int, Collection<int, App\User>>
$keyed->groupBy('id', true); // Collection<int, Collection<string, App\User>>
```

## Higher order proxies

The proxy forms resolve the same way as the argument forms:

```php
$users->groupBy->email; // Collection<string, Collection<int, App\User>>
$users->keyBy->email;   // Collection<string, App\User>
```
