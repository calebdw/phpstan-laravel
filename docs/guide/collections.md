# Collections

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

The higher order proxy forms resolve the same way as the argument forms:

```php
$users->groupBy->email; // Collection<string, Collection<int, App\User>>
$users->keyBy->email;   // Collection<string, App\User>
```
