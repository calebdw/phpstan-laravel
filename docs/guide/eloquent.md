# Builders, factories and collections

Three places where Laravel resolves a class at runtime that static analysis
cannot guess. In each case naming the class on the model, and documenting the
generic, is what makes the chain type check.

## Custom model builders

A custom builder gives better analysis than model scopes do, and keeps the model
class smaller. Point the model at it with Laravel's attribute:

```php
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends Builder<User> */
class UserBuilder extends Builder
{
    /** @return $this */
    public function active(): static
    {
        return $this->where('active', true);
    }
}

#[UseEloquentBuilder(UserBuilder::class)]
class User extends Model
{
}
```

```php
User::query()->active()->get();  // EloquentCollection<int, User>
```

The `@extends Builder<User>` on the builder is what ties it to the model, and it
is the one annotation you do need. The attribute carries the rest, so there is no
trait to add and no generic to repeat.

The `HasBuilder` trait works too, if you prefer it or are already using it. It
needs its generic documented, since that is where the builder type comes from:

```php
class User extends Model
{
    /** @use HasBuilder<UserBuilder> */
    use HasBuilder;

    protected static string $builder = UserBuilder::class;
}
```

## Model factories

`#[UseFactory]` is resolved the same way, so a factory needs no repeated
generic either. The `HasFactory` trait is still required, since that is what
puts `factory()` on the model:

```php
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [];
    }
}

#[UseFactory(UserFactory::class)]
class User extends Model
{
    use HasFactory;
}
```

```php
User::factory();           // App\UserFactory
User::factory()->create(); // App\User
```

The `@extends Factory<User>` on the factory is what says which model it builds,
so a factory pointed at the wrong model is caught rather than assumed.

!!! note "At level 6 and above, document the trait's generic too"

    `HasFactory` is generic, so `missingType.generics` asks for its type
    parameter whether or not the attribute is present. Adding it costs one line
    and silences that:

    ```php
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    ```

    The `$factory` property and a `newFactory()` override are both understood as
    well, and take precedence in that order.

## Custom model collections

Same shape. `#[CollectedBy]` is resolved, so a custom collection needs no trait:

```php
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** @extends Collection<array-key, User> */
final class UserCollection extends Collection
{
}

#[CollectedBy(UserCollection::class)]
class User extends Model
{
}
```

```php
User::all();  // App\UserCollection
```

The `HasCollection` trait is equally understood, and is what you want when one
collection serves several models, since the generic can then say which:

```php
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
