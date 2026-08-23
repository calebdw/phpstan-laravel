# Eloquent rules

Mistakes specific to models, relations and the query builder.

Each heading below gives the error identifier first, then the option that
switches the rule on or off, then its default.

## Model make

`laravel.modelMake` &middot; option `rules.modelMake` &middot; on by default

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

## Model appends

`laravel.modelAppends` &middot; option `rules.modelAppends` &middot; on by default

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

`laravel.modelMethodVisibility.scope`, `laravel.modelMethodVisibility.accessor` &middot; option `rules.modelMethodVisibility` &middot; off by default

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

## Model forwarding to builder

`laravel.modelForwardingToBuilder` &middot; option `rules.modelForwardingToBuilder` &middot; off by default

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

`laravel.modelStaticForwardingToBuilder` &middot; option `rules.modelStaticForwardingToBuilder` &middot; off by default

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

## Relation existence

`laravel.relationExistence` &middot; always enabled

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
