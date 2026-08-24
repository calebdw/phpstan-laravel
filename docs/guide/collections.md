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

## only

`Arr::only` narrows an array shape to the keys you ask for. A key the array does
not have is dropped, since the call is a key intersection:

```php
/** @var array{id: int, name: string, email: string} $row */
Arr::only($row, ['id', 'name']);  // array{id: int, name: string}
Arr::only($row, 'id');            // array{id: int}
Arr::only($row, ['id', 'nope']);  // array{id: int}
```

Keys that are not known while analysing cannot be intersected. Since the call
can only ever drop entries, the result keeps the shape with every entry optional
rather than falling back to `array<string, mixed>`:

```php
Arr::only($row, $keys);  // array{id?: int, name?: string, email?: string}
```

Where there is no shape to intersect, the keys still narrow the key type:

```php
/** @var array<string, int> $map */
Arr::only($map, ['a', 'b']);  // array<'a'|'b', int>
```

!!! warning "No dot notation"

    `Arr::only` is an `array_intersect_key` over the top-level keys, so a dotted
    key matches a literal key that happens to contain a dot, and never a nested
    one. `Collection::only` behaves the same way:

    ```php
    Arr::only($nested, ['user.name']);  // array{}
    ```

    That is what the framework returns at runtime, so the inferred type is
    reporting the bug rather than causing it. Reach for `Arr::get` or `data_get`
    when you want a path, or `pluck`, which does resolve one.

`Model::only` builds a shape too, but out of a model's attributes, and answers
differently for a key that is not there. See
[reading a subset of attributes](model-properties.md#reading-a-subset-of-attributes).

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

## Precision, and widening it where you want it

Keys and values are resolved as precisely as the input allows. A grouper
returning a backed enum's value gives the literal union, and an interpolated key
gives the product of its parts:

```php
$items->groupBy(fn ($i) => $i->priority->value);          // Collection<10|20, ...>
$items->keyBy(fn ($i) => "{$i->a->value}|{$i->b->value}"); // Collection<'10|x'|'10|y'|..., ...>
```

That precision is not decoration. It survives to wherever you consume the
collection, so a refined key still reads as refined:

```php
foreach ($items->keyBy(fn ($i) => "row-{$i->id}") as $key => $item) {
    // $key is non-falsy-string, not string
}
```

`Collection` declares `TKey` invariantly, so an exact key type is what an
annotation has to match. Where you would rather accept the general type, ask for
it at the annotation with `covariant`:

```php
/** @return Collection<covariant string, Item> */
public function keyed(): Collection
{
    return $this->items->keyBy(fn ($i) => "{$i->a->value}|{$i->b->value}");
}
```

That is use-site variance. Values need it far less often, since `TValue` is
covariant in the stubs as it is in the framework, so a
`Collection<int, non-falsy-string>` already satisfies a
`Collection<int, string>` annotation.

`array-key` also works for a key, being a benevolent union. A plain
`int|string` does not, despite reading like the safer choice: it is matched
invariantly and accepts neither `int` nor `string`.

## Higher order proxies

The proxy forms resolve the same way as the argument forms:

```php
$users->groupBy->email; // Collection<string, Collection<int, App\User>>
$users->keyBy->email;   // Collection<string, App\User>
```
