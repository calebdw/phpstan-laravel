# Collection rules

Two redundancy checks: work handed to a collection that the database or a
cheaper method should have done instead.

## Unnecessary collection call

`laravel.unnecessaryCollectionCall` &middot; option `rules.unnecessaryCollectionCall.enabled` &middot; on by default

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

`laravel.unnecessaryEnumerableToArrayCall` &middot; option `rules.unnecessaryEnumerableToArrayCall` &middot; on by default

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
