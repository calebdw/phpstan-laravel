# Builders, factories and collections

Three places where Laravel resolves a class at runtime that static analysis
cannot guess. In each case naming the class on the model, and documenting the
generic, is what makes the chain type check.

## Custom model builders

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

## Model factories

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

## Custom model collections

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

