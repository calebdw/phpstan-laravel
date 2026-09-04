# How This Differs From Larastan

This package began as a fork of [larastan/larastan][larastan] and owes it
everything. It is no longer a patch set carried on top: it has its own
namespace, its own configuration surface, its own release policy, and a good
deal of inference that Larastan does not have.

If you are moving an existing project across, [migrating from
Larastan](../migrating-from-larastan.md) covers the mechanics. This page is about
whether you want to.

## Model metadata comes from a real model

This one is worth explaining first, because a lot of the rest follows from it.

Model metadata is read from an actual instantiated model rather than by reading
the class. Constructing the model runs Laravel's own boot sequence, so
`bootTraits()` and `initializeTraits()` run, which means **anything a trait
contributes is visible**: casts, appends, dates, key type, incrementing,
fillable, table name.

If a model cannot be constructed, because its constructor needs something the
analysis environment does not have, it is built without running the
constructor instead. Everything the class itself declares is still read, so
analysis degrades rather than stopping; what is lost is only what the boot
sequence would have added.

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

### PostgreSQL schema dumps

PostgreSQL plain-text schema dumps are supported directly through
`calebdw/pg-schema-parser`. PostgreSQL enums retain their labels as literal
string unions, domains resolve to their underlying types, arrays reflect the
string values returned by PDO, and tables outside `public` keep their
schema-qualified names.

Larastan's schema parsing is built around MySQL parsers, which do not reliably
handle PostgreSQL dumps. Set `sqlParser: postgres`, or let `auto` select it when
it is the only installed parser.

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

Laravel's relation constraints take a closure and hand it a query builder, but
the signature promises only a `Closure`. Everything inside is `mixed`, so
nothing you write there is checked. This is where a great deal of query code
lives, and it is the largest single gain over Larastan, which does not type
these at all.

```php
User::query()->whereHas('accounts', function ($query) {
    // Illuminate\Database\Eloquent\Builder<App\Account>
    $query->where('active', true);
});
```

The relation is resolved from the model the query is on, so the closure needs
no parameter type of its own.

**Dotted paths are walked.** Each segment resolves against the model the
previous one pointed at, and the closure is typed for the last:

```php
Product::query()->whereHas('stocks.warehouse', function ($query) {
    // App\WarehouseBuilder
    $query->active();
});
```

That example is worth reading twice. The parameter is `WarehouseBuilder`, not
`Builder<Warehouse>`, because `Warehouse` declares a custom builder, so a scope
that exists only on your builder is callable inside the closure.

**The whole family is covered**, not `whereHas` alone: `has`, `doesntHave`,
`whereHas`, `withWhereHas`, `orWhereHas`, `whereDoesntHave`,
`orWhereDoesntHave`, `whereRelation`, `orWhereRelation`, and the eight `*Morph`
variants. Position does not matter either, so the closure in
`has('stocks', '>=', 1, 'and', fn ($q) => ...)` is typed as well.

**`withWhereHas` receives both**, because Laravel uses the argument as a
constraint and as an eager load:

```php
Product::query()->withWhereHas('stocks', function ($query) {
    // Builder<App\Stock>|HasMany<App\Stock, App\Product>
});
```

**Morph methods give a union, and a typed `$type`.** The second argument names
the models it may be, so the closure is typed for all of them:

```php
Product::query()->whereHasMorph('stocks', [Warehouse::class, Stock::class], function ($query, $type) {
    // $query: App\WarehouseBuilder|Builder<App\Stock>
    // $type:  string
});
```

`through()` carries its generics as well:

```php
$user->through('mechanic');
// Illuminate\Database\Eloquent\PendingHasThroughRelationship<..., App\User>
```

and `when()` / `unless()` on a relation keep the relation's type instead of
widening to the base class.

#### Where it stops

This is not finished work, and it is worth knowing the edges.

The relation name has to be a literal the analyser can see. Passed a variable
it cannot fold to a string, there is nothing to resolve, and the parameter
stays `Builder<Model>`. The relation method also has to declare its generics:
a `@return HasMany<Stock, $this>` is what names the related model, and an
undocumented relation gives `Builder<Model>` as well. Neither of those is a
false positive, only a return to what you would have had anyway.

The real gap is eager loading. Closures passed to `with()` are **not** typed,
and a closure that narrows its own parameter is reported, because Laravel
documents that argument as `Closure(Relation<*, *, *>): mixed` and a narrower
parameter is not a subtype of a wider one:

```php
Product::query()->with([
    'stocks' => function (MorphTo $morphTo) {   // argument.type
        $morphTo->morphWith([Warehouse::class => ['stocks']]);
    },
]);
```

Fixing that properly needs a change further down, in PHPStan itself. Until
then, widen the parameter to `Relation` or ignore `argument.type` at that call.

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
argument rather than of the signature, so no stub can describe it: Laravel's
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
level, including callbacks that declare no types:

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

!!! note

    An array argument means different things across these methods. For `pluck`
    and `keyBy` it is the segments of a single key, so `['user', 'name']` reads
    `user.name`. For `groupBy` it is successive grouping levels.

### countBy, value, sum, min, max, collapse

The same column lookup drives these. `countBy` keys the counts, `value` reads
the first item, and the aggregates stop being `mixed` the moment a column or
callback is involved:

```php
$users->countBy('email'); // Collection<string, int>
$users->value('name');    // string|null
$users->sum('id');        // int
$users->min('email');     // string|null
```

`mapToGroups` uses the same callback inference, and Eloquent collections
`toBase()` the outer collection the way they do at runtime:

```php
$users->mapToGroups(fn ($u) => ['foo' => $u->id]);
// Collection<'foo', EloquentCollection<int, int>>
```

`transform` rewrites `$this` through `@phpstan-this-out`. `toArray` walks
Arrayable items instead of `array<TKey, mixed>`. `flatten` respects a known
depth, and `dot` collapses nested arrays to `Collection<string, leaf>`:

```php
$users->transform(fn (User $u) => $u->email); // $users is Collection<int, string>
$users->toArray();                           // array<int, array<string, mixed>>
$nested->flatten(1);                         // one level unwrapped
$rows->dot();                                // Collection<string, int|string>
```

`collapse` unwraps one level instead of widening to `mixed`:

```php
/** @var Collection<int, Collection<string, User>> $nested */
$nested->collapse();         // Collection<int, User>
$nested->collapseWithKeys(); // Collection<string, User>
```

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
keeps both halves rather than collapsing to one. That second one matters if you
return a collection that both extends `Collection` and implements an interface
of your own:

```php
class User extends Model
{
    /** @return Collection<int, static>&TreeLikeCollection<static> */
    public function newCollection(array $models = []): Collection&TreeLikeCollection
    {
        return new TreeCollection($models);
    }
}

User::all();
// Collection<int, User>&TreeLikeCollection<User>

User::all()->getTree();
// Collection<int, User>&TreeLikeCollection<User>
```

Calling `getTree()`, which comes from the interface half, does not cost you the
collection half.

### Paginators

A paginator over models hands back an Eloquent collection:

```php
User::paginate()->getCollection();
// Illuminate\Database\Eloquent\Collection<int, App\User>
```

## Managers

An `Illuminate\Support\Manager` subclass forwards unknown calls to its driver,
so the driver decides what the manager can do. Which driver that is, and what
type it has, is read from the `create{Driver}Driver()` method rather than taken
from the object the container happens to build:

```php
class NotifierManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('notifier.default');
    }

    protected function createSlackDriver(): Notifier
    {
        return new SlackNotifier();
    }
}

$manager->driver();
// App\Notifier

$manager->driver('slack');
// App\Notifier

$manager->send($message);
// checked against Notifier, not against SlackNotifier
```

Two things follow from reading the declared type. The driver is never
constructed, so one that needs credentials, a connection or a config key absent
from your environment costs nothing and cannot fail the run. And a creator
returning a contract exposes only that contract: a method the implementation has
and the interface does not is reported as undefined, which is what declaring the
return type asked for.

Larastan resolves the manager and calls `driver()` for real, then uses the class
of whatever came back, so the contract is ignored and every public method of the
implementation is callable.

A creator with no declared return type still falls back to resolving the driver
from the container, so managers written before the framework had return types
keep working:

```php
protected function createSlackDriver()
{
    return new SlackNotifier();
}

$manager->driver();
// App\SlackNotifier
```

The driver's name is the one part that still comes from the manager itself,
since `getDefaultDriver()` usually reads config. When it cannot be determined,
every driver the manager declares is considered instead, so a method belonging
to any of them is accepted rather than none of them.

## Configuration

Typed configuration is not new: Larastan reads your config files too, behind
its `checkConfigTypes` option. Two things differ.

### It is on, and it asks the container first

There is no option to enable, because a `config()` call that returns `mixed` is
not useful to anybody. Where the application boots, the container answers, and
file parsing fills in only what the container could not, which is what makes it
work for packages that have no application to boot.

```php
config('auth.defaults');
// array{guard: string, passwords: string}

config('auth.defaults.guard');
// string|null
```

### The typed accessors are checked

`string()`, `integer()`, `float()`, `boolean()`, `array()` and `collection()`
each throw when the value is not already of that type: none of them coerce.
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
        migrationDirectories:
            - database/migrations
            - modules/*/database/migrations
```

A configured list replaces the conventional directory. Including
`database/migrations` above retains application migrations while adding the
module migrations; omit it when the modules are the only source.

Like Laravel's migrator, each matched migration directory is scanned directly
rather than recursively. Nested directories are only included when another
configured path explicitly matches them.

Relative directory options resolve against the PHPStan config file that
declares them, rather than against whatever directory you happened to run from.

A schema dump the parser cannot read fails the run rather than being skipped
silently, because a skipped dump means missing model properties and confusing
errors later with nothing pointing back at the cause.

## Rules and options

Every rule is individually toggleable and every error carries a `laravel.`
prefixed identifier, so you can ignore a category precisely. See
[rules](../rules/index.md) for the full list. Ones Larastan does not have:

- **`modelForwardingToBuilder`** and **`modelStaticForwardingToBuilder`**
  (both off) for codebases that prefer `User::query()->where(...)` to
  `User::where(...)`.
- **`configAccessor`** (on), described above.
- **`strictContracts`** (off). By default `resolve(SomeContract::class)`
  infers the concrete class the container is bound to, which is convenient but
  lets code drift onto methods only one implementation happens to have. Enable
  it to take the contract at face value:

  ```php
  resolve(Repository::class);
  // off: Illuminate\Config\Repository
  // on:  Illuminate\Contracts\Config\Repository
  ```

## Packaging

- **The SQL parser is optional.** Squashed schema dumps need one, but it is not
  a hard dependency. MySQL dumps support `iamcal/sql-parser` (MIT) and
  `phpmyadmin/sql-parser` (GPL-2.0-or-later); PostgreSQL dumps support
  `calebdw/pg-schema-parser` (MIT). Name one with
  [`sqlParser`](../reference/configuration.md#sqlparser) or let it pick.
- **Options nest under `laravel:`** rather than being mixed into PHPStan's own
  `parameters`, with rule toggles a level deeper under `laravel.rules`. The
  whole tree is schema validated, so a typo fails the run---with a suggested
  spelling---instead of being silently ignored.
- **Error identifiers are `laravel.*`** rather than `larastan.*`, and each one
  names what was found rather than the policy behind it: `laravel.modelMake`,
  not `larastan.noModelMake`. Where a single concern reports more than one kind
  of error, the identifiers are grouped —
  `laravel.modelMethodVisibility.scope` and `.accessor`.
- **The namespace is `CalebDW\PhpstanLaravel\`.**
- **A narrower support policy**: PHP 8.3+, and the two most recent Laravel
  majors at recent minors. Fewer version shims means less dead code and less
  hedged inference.

## Release policy

This will not show up in a feature list, but it may matter most.

New errors appearing because the analysis got better are **not** treated as a
breaking change here. A red build is not a broken application, which is the
entire reason to run static analysis in CI behind a lock file.

New rules are a separate case, and they ship off by default in minor releases,
so adding one reports nothing until you enable it. Turning a rule on by default
waits for a major version.

The practical consequence is that improvements ship rather than waiting on a
major version, while what your build reports stays under your control. The full
policy is in [backward compatibility](../about/backward-compatibility.md).

## Tooling

If you use [Laravel Boost][boost], this package ships an AI guideline and a
`phpstan-laravel-analysis` skill, so an agent working in your project knows how
to run the analysis and how to read what comes back.

A second skill, `phpstan-laravel-larastan-migration`, performs the switch
itself. The renames in [the migration
guide](../migrating-from-larastan.md)---options, identifiers in baselines and
inline ignores---are mechanical enough to hand off, so an agent can do them and
then verify the result against the error count from before.

<!-- links -->
[larastan]: https://github.com/larastan/larastan
[boost]: https://github.com/laravel/boost

## Acknowledgments

Larastan was created by [Can Vural](https://github.com/canvural) and [Nuno
Maduro](https://github.com/nunomaduro), and improved by many contributors over
the years. This package builds directly on that work and remains MIT licensed.
