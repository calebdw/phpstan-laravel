# How This Differs From Larastan

This package began as a fork of [larastan/larastan][larastan] and owes it
everything. It is no longer a patch set carried on top: it has its own
namespace, its own configuration surface, its own release policy, and a good
deal of inference that Larastan does not have.

If you are moving an existing project across, [migrating from
Larastan](migrating-from-larastan.md) covers the mechanics. This page is about
whether you want to.

## Model metadata comes from a real model

This one is worth explaining first, because a lot of the rest follows from it.

Model metadata is read from an actual instantiated model rather than by reading
the class. Constructing the model runs Laravel's own boot sequence, which means
`bootTraits()` and `initializeTraits()` run, which means **anything a trait
contributes is visible** — casts, appends, dates, key type, incrementing,
fillable, table name.

That generalises well beyond the framework's own traits. A cast registered by a
trait in a third-party package is understood, because the model was built the
same way Laravel builds it:

```php
trait HasPreferences
{
    public function initializeHasPreferences(): void
    {
        $this->casts['preferences'] = AsCollection::class;
    }
}

class User extends Model
{
    use HasPreferences;
}

$user->preferences;
// Illuminate\Support\Collection<array-key, mixed>
```

The framework traits come out of it for free. `HasUuids` and `HasUlids` change
the key's type and turn off incrementing, so:

```php
class Order extends Model
{
    use HasUlids;
}

$order->id;
// string, not int
```

## Eloquent

### Multiple database connections

Models on a second connection resolve their properties from *that* connection's
migrations and schema dumps. Tables are tracked per connection rather than
flattened into one namespace, so two connections may both have a `users` table
with different columns and each model sees the right one.

```php
// database/migrations/..._create_users_table.php
Schema::create('users', fn (Blueprint $t) => $t->string('email'));
Schema::connection('reporting')->create('users', fn (Blueprint $t) => $t->integer('hits'));

class ReportingUser extends Model
{
    protected $connection = 'reporting';
    protected $table = 'users';
}

$user->email;          // string
$reportingUser->hits;  // int
$reportingUser->email; // error: property does not exist
```

Schema dumps are matched per connection too, by the
`{connection}-schema.sql` filename Laravel writes.

### Custom builders survive the chain

A model declaring its own builder keeps that builder through inherited methods,
rather than degrading to `Builder<Model>` at the first `where()`:

```php
class Team extends Model
{
    public function newEloquentBuilder($query): TeamBuilder
    {
        return new TeamBuilder($query);
    }
}

Team::query()->where('name', 'A')->orderBy('name');
// App\TeamBuilder

Team::query()->where('name', 'A')->get();
// Illuminate\Database\Eloquent\Collection<int, App\Team>
```

Forwarding between a model, its builder, and a custom builder was reworked
broadly, along with the builder stubs themselves, so static calls on the model
and instance calls on the builder agree about what comes back.

### Relation closures get typed parameters

The closure passed to a relation constraint receives the related model's
builder, so what you write inside it is checked rather than being `mixed`:

```php
User::whereHas('accounts', function ($query) {
    // Illuminate\Database\Eloquent\Builder<App\Account>
    $query->where('active', true);
});
```

`through()` carries its generics as well:

```php
$user->through('mechanic');
// Illuminate\Database\Eloquent\PendingHasThroughRelationship<..., App\User>
```

and `when()` / `unless()` on a relation keep the relation's type instead of
widening to the base class.

### Factories stay on your factory

`has*` and `for*` return `static`, so a chain off a custom factory does not
fall back to the base factory:

```php
User::factory()->hasAccounts(3);
// Database\Factories\UserFactory, not Illuminate\...\Factory

User::factory()->createOne();  // App\User
User::factory()->createMany([]); // Collection<int, App\User>
```

### Dates

Date casts resolve to the configured date class rather than a bare string, and
the `Date` facade returns it too:

```php
$user->created_at;   // Illuminate\Support\Carbon
Date::create();      // Illuminate\Support\Carbon
```

### Accessors

`Attribute`'s `TGet` is covariant, which matters anywhere an accessor's type
flows into a narrower position. Both accessor styles are supported — the
`Attribute` form and the legacy `getFooAttribute()` methods — including when a
same-named method shadows the legacy one.

## Collections

### pluck

Resolved from the column rather than left as `mixed`:

```php
// before
User::pluck('name', 'id');        // Collection<(int|string), mixed>
Arr::pluck($users, 'name', 'id'); // array<int, User>   (wrong)

// now
User::pluck('name', 'id');        // Collection<int, string>
User::all()->pluck('name', 'id'); // Collection<int, string>
Arr::pluck($users, 'name', 'id'); // array<string, string>
```

Dotted nesting, arrays of segments, and closures all work, including closures
that declare no types at all, because the parameter is typed from the
collection's value type:

```php
$posts->pluck('user.name');        // Collection<int, string>
$posts->pluck(['user', 'name']);   // Collection<int, string>
$users->pluck(fn ($u) => $u->id);  // Collection<int, int>
```

`Arr::pluck` without a key is a `list`, because that is what it returns:

```php
Arr::pluck($users, 'name');  // list<string>
```

### keyBy

Resolved the same way. Unlike `pluck` it rewrites only the keys, so the value
type and the collection class both carry over:

```php
$users->keyBy('name');            // EloquentCollection<string, App\User>
$users->keyBy(fn ($u) => $u->id); // EloquentCollection<int, App\User>
$posts->keyBy('user.name');       // EloquentCollection<string, App\Post>
Arr::keyBy($users, 'name');       // array<string, App\User>
```

### groupBy

`groupBy` returns a collection of collections, and an array argument groups
again inside each group, once per element. That depth is a property of the
argument rather than of the signature, so no stub can describe it — Laravel's
own conditional type widens the values to `mixed` the moment an array is
involved.

Here the nesting follows the argument, one level per grouper. Every level is the
receiver's own class, since `groupBy` builds each group with `newInstance()` —
abbreviated to `Collection` below to keep the nesting readable:

```php
$users->groupBy('name');
// Collection<string, Collection<int, User>>

$users->groupBy(['name', 'id']);
// Collection<string, Collection<int, Collection<int, User>>>

$users->groupBy(['name', 'id', 'email']);
// Collection<string, Collection<int, Collection<string, Collection<int, User>>>>
```

Group keys are resolved rather than widened to `array-key`, through the same
lookup `pluck` and `keyBy` use, so dotted paths and callbacks work at every
level — including callbacks that declare no types:

```php
$users->groupBy('id');                        // Collection<int, ...>
$posts->groupBy('user.name');                 // Collection<string, ...>
$users->groupBy(fn ($u) => $u->id);           // Collection<int, ...>
$users->groupBy(['name', fn ($u) => $u->id]); // a column and a callback
```

`groupBy` normalizes each key before using it, and so does this:

```php
$users->groupBy(fn ($u) => $u->id > 5);        // bool becomes int
$users->groupBy(fn ($u): Stringable => ...);   // Stringable becomes string
$users->groupBy(fn ($u): array => [$u->name]); // several groups per item
```

`preserveKeys` affects only the innermost collection, which is why it shows up
only where the collection's own keys are not already `int`:

```php
/** @var Collection<string, User> $keyed */
$keyed->groupBy('id');        // Collection<int, Collection<int, User>>
$keyed->groupBy('id', true);  // Collection<int, Collection<string, User>>
```

> [!NOTE]
> An array argument means different things across these methods. For `pluck`
> and `keyBy` it is the segments of a single key, so `['user', 'name']` reads
> `user.name`. For `groupBy` it is successive grouping levels.

### Higher order proxies agree with the argument forms

`$users->groupBy->email` and `$users->groupBy('email')` resolve the same key
type, rather than the proxy form falling back to `array-key`:

```php
$users->groupBy->email;  // Collection<string, Collection<int, User>>
$users->keyBy->email;    // Collection<string, User>
```

### Template types

Collection template parameters are no longer overwritten when chaining through
methods that should preserve them, and a collection typed as an intersection
keeps both halves rather than collapsing to one.

### Paginators

A paginator over models hands back an Eloquent collection:

```php
User::paginate()->getCollection();
// Illuminate\Database\Eloquent\Collection<int, App\User>
```

## Configuration

### Config values have shapes

`config()` resolves against your actual configuration rather than returning
`mixed`:

```php
config('auth.defaults');
// array{guard: string, passwords: string}

config('auth.defaults.guard');
// string|null

Config::array('auth.defaults');
// array{guard: string, passwords: string}
```

### Packages get config typing too

Analysing a package boots no application, so there is no container to ask.
Point `configDirectories` at your config files and keys are resolved by parsing
them instead — no Testbench workbench required:

```neon
parameters:
    laravel:
        configDirectories:
            - config
```

Parsing is lazy and cached, so only the files actually referenced are read, and
each is read once. Where an application *is* bootable the container answers
first, so this can only fill in keys that were previously `mixed`.

### The typed accessors are checked

`string()`, `integer()`, `float()`, `boolean()`, `array()` and `collection()`
each throw when the value is not already of that type — none of them coerce.
Reading a key of the wrong type is a guaranteed runtime failure, and is now
reported:

```php
Config::string('auth.defaults.guard'); // fine
Config::array('auth.defaults.guard');  // error: is string, requires an array
Config::float('auth.password_timeout'); // error: is int, requires a float
```

## Paths and scanning

Migration and schema paths accept wildcards, which modular applications need:

```neon
parameters:
    laravel:
        databaseMigrationsPath:
            - modules/*/database/migrations
```

Relative directory options resolve against the PHPStan config file that
declares them, rather than against whatever directory you happened to run from.

A schema dump the parser cannot read fails the run rather than being skipped
silently, because a skipped dump means missing model properties and confusing
errors later with nothing pointing back at the cause.

## Rules and options

Every rule is individually toggleable and every error carries a `laravel.`
prefixed identifier, so you can ignore a category precisely. See
[rules](rules.md) for the full list. Ones Larastan does not have:

- **`noModelForwardingToBuilder`** and **`noModelStaticForwardingToBuilder`**
  (both off) for codebases that prefer `User::query()->where(...)` to
  `User::where(...)`.
- **`checkConfigAccessors`** (on), described above.
- **`checkStrictContracts`** (off). By default `resolve(SomeContract::class)`
  infers the concrete class the container is bound to, which is convenient but
  lets code drift onto methods only one implementation happens to have. Enable
  it to take the contract at face value:

  ```php
  resolve(Repository::class);
  // off: Illuminate\Config\Repository
  // on:  Illuminate\Contracts\Config\Repository
  ```

## Packaging

- **The SQL parser is optional.** Squashed schema dumps need one, but neither is
  a hard dependency, so the license entering your dependency tree is your
  choice: `iamcal/sql-parser` (MIT) or `phpmyadmin/sql-parser`
  (GPL-2.0-or-later). Name one with
  [`sqlParser`](custom-config-parameters.md#sqlparser) or let it pick.
- **Options nest under `laravel:`** rather than being mixed into PHPStan's own
  `parameters`, and are schema validated, so a typo fails the run instead of
  being silently ignored.
- **Error identifiers are `laravel.*`** rather than `larastan.*`.
- **The namespace is `CalebDW\PhpstanLaravel\`.**
- **A narrower support policy**: PHP 8.3+, and the two most recent Laravel
  majors at recent minors. Fewer version shims means less dead code and less
  hedged inference.

## Release policy

This will not show up in a feature list, but it may matter most.

New errors appearing because the analysis got better are **not** treated as a
breaking change here, and neither is adding a rule. A red build is not a broken
application, which is the entire reason to run static analysis in CI behind a
lock file.

The practical consequence is that improvements ship, rather than waiting for a
major version because someone's pipeline might go red. The full policy is in
[backward compatibility](backward-compatibility.md).

## Tooling

If you use [Laravel Boost][boost], this package ships an AI guideline and a
`phpstan-laravel-analysis` skill, so an agent working in your project knows how
to run the analysis and how to read what comes back.

<!-- links -->
[larastan]: https://github.com/larastan/larastan
[boost]: https://github.com/laravel/boost
