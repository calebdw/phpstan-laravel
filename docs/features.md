# Features

All features that are specific to Laravel applications are listed here.

## Model Properties

Your migrations and schema dumps are scanned to work out each table's columns,
which is how the magic properties on an Eloquent model get their types. Columns
are tracked per connection, so a model on a second connection resolves against
that connection's tables.

```php
$user->email;      // string
$user->created_at; // Illuminate\Support\Carbon
$user->emial;      // error: property does not exist
```

The rest of a model's metadata — casts, appends, dates, key type, incrementing,
fillable, table name — is read from an instantiated model rather than from the
class, so Laravel's own boot sequence runs and anything a trait contributes is
visible. A cast registered by a trait in a third-party package is understood for
the same reason `HasUlids` is.

Where migrations live somewhere unconventional, or a table is built in a way the
scanner cannot follow, point the
[path options](custom-config-parameters.md#migrationdirectories) at the right
directories. Enabling
[`checkModelProperties`](custom-config-parameters.md#checkmodelproperties) then
checks property *names* passed to methods, catching typos in things like
`User::create([...])`.

## Accessors and Mutators

Both styles are recognized. An [`Attribute`][attributes] accessor must be a
`protected` method annotated with the `Attribute` generic types: the first is
the getter's return type, the second the setter's argument type.

#### Examples

```php
<?php
/** @return Attribute<string[], string[]> */
protected function scopes(): Attribute
{
    return Attribute::make(
        get: fn (?string $value) => is_null($value) ? [] : explode(' ', $value),
        set: function(array $value) {
            $set = array_unique($value);
            sort($set);
            return ['scopes' => implode(' ', $set)];
        }
    );
}
```

```php
<?php
/** @return Attribute<bool, never> */
protected function isTrue(): Attribute
{
    return Attribute::make(
        get: fn (?string $value): bool => $value === null,
    );
}
```

The older `getFooAttribute()` style is supported as well, and its return type is
used directly. It is still found when a method of the same camel case name
exists alongside it:

```php
<?php
public function getFullNameAttribute(): string
{
    return $this->first_name . ' ' . $this->last_name;
}
```

[attributes]: https://laravel.com/docs/eloquent-mutators#accessors-and-mutators

## Custom Model Builders

Custom builders offer a better static analysis experience than using model scopes, and they help slim down the model class.

Here's an example of how to create a custom builder class:

```php
<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\HasBuilder;
use Illuminate\Database\Eloquent\Model;

/** @extends Builder<User> */
class UserBuilder extends Builder
{
    /** @return $this */
    public function active(): static
    {
        $this->where('active', true);

        return $this;
    }
}

class User extends Model
{
    /** @use HasBuilder<UserBuilder> */
    use HasBuilder;

    protected static string $builder = UserBuilder::class;
}

// Usage
$users = User::query()
        ->active()
        ->get();
```

> [!NOTE]
> The `HasBuilder` trait was introduced in Laravel 11, if you are using an older version of Laravel you can use the following:
>
> ```php
> <?php
> class User extends Model
> {
>    public static function query(): UserBuilder
>    {
>        return parent::query();
>    }
>
>    /** @param  \Illuminate\Database\Query\Builder  $query */
>    public function newEloquentBuilder($query): UserBuilder
>    {
>        return new UserBuilder($query);
>    }
> }
> ```

## Model Factories

Because the `Factory` class is generic, you need to specify the template type in your model factories.
And while Laravel has magic to automatically associate a factory with a model, you'll have a much better static analysis experience if you specify the factory class in the model.

So for example, here's how the classes can look:

```php
<?php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;
}

class User extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected static string $factory = UserFactory::class;
}
```

> [!NOTE]
> The `HasFactory` generics was introduced in Laravel 11, if you are using an older version of Laravel you can use the following:
>
> ```php
> <?php
> class User extends Model
> {
>    /**
>     * @param  (callable(array<string, mixed>, static|null): array<string, mixed>)|array<string, mixed>|int|null  $count
>     * @param  (callable(array<string, mixed>, static|null): array<string, mixed>)|array<string, mixed>  $state
>     */
>    public static function factory($count = null, $state = []): UserFactory
>    {
>        return parent::factory();
>    }
>
>    protected static function newFactory(): UserFactory
>    {
>        return UserFactory::new();
>    }
> }
> ```

## Custom Model Collections

Custom collections can be created to extend the functionality of the default collection class.

So for example, here's how the classes can look:

```php
<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\HasCollection;
use Illuminate\Database\Eloquent\Model;

/** @extends Collection<array-key, User> */
final class UserCollection extends Collection
{
}

class User extends Model
{
    /** @use HasCollection<UserCollection> */
    use HasCollection;

    protected static string $collectionClass = UserCollection::class;
}
```

Or if the collection is used for multiple models then you need to create a generic collection class
and then specify the template type in the model.

```php
<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\HasCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TKey of array-key
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends Collection<TKey, TModel>
 */
class GeneralCollection extends Collection
{
}

class User extends Model
{
    /** @use HasCollection<GeneralCollection<int, static>> */
    use HasCollection;

    protected static string $collectionClass = GeneralCollection::class;
}
```

> [!NOTE]
> The `HasCollection` trait was introduced in Laravel 11, if you are using an older version of Laravel you can use the `newCollection` method to override the collection class:
>
> ```php
> <?php
> class User extends Model
> {
>     /**
>      * Create a new Eloquent Collection instance.
>      *
>      * @param  array<array-key, \Illuminate\Database\Eloquent\Model>  $models
>      * @return GeneralCollection<int, static>
>      */
>     public function newCollection(array $models = []): GeneralCollection
>     {
>         return new GeneralCollection($models);
>     }
> }
> ```

## Model Relationships

Relationship types are read from the relation method's declared return type, so
that type has to carry its generic parameters. The class the relation points at
is taken from there rather than from the `hasMany(Post::class)` argument, which
means an undocumented relation resolves to the base relation class and the
related model is lost.

```php
/** @return BelongsTo<User, $this> */
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

/** @return HasMany<Post, $this> */
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

## Collections

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
