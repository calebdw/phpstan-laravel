# Frequently asked questions

## Does this run my application?

Yes, it boots it. Booting registers every service provider, which is the only
way to know what your container is bound to, what your config actually contains
and where your views live. It is the same thing `php artisan` does before it
runs a command.

It does not handle a request or run a query. Controllers, jobs and listeners are
never constructed.

Some of your code does run, though: every service provider, anything those
providers resolve, and every console command, each with its constructor
dependencies. [Booting your application](../guide/booting.md) covers what that
means and how to guard work that must not repeat.

## Can I stop something running during analysis?

Yes. PHPStan defines `__PHPSTAN_RUNNING__`, so a provider can skip work that
cannot safely happen on every run:

```php
if (defined('__PHPSTAN_RUNNING__')) {
    return;
}
```

The constant is PHPStan's, not this package's. See [when something really must
not run](../guide/booting.md#when-something-really-must-not-run).

## Why does it need a database connection?

It does not. Columns come from reading your migration files and schema dumps as
source code, never from connecting to a database. You can analyse a project
with no database available at all.

## A property exists but is reported as missing

Almost always the extension could not see how the table was built. In order of
likelihood:

1. Your migrations are somewhere other than `database/migrations`. Point
   [`migrationDirectories`](../reference/configuration.md#migrationdirectories)
   at them.
2. The column is added by something the scanner cannot follow, such as a raw
   `DB::statement()` call or a package's migration published at install time.
3. The table comes from a schema dump the parser could not read.
4. The model uses a second connection whose migrations are elsewhere.

Adding a `@property` annotation is the correct fix for cases the scanner
genuinely cannot reach, such as a view or a table owned by another service.
Reach for it after ruling out the first item, not before.

## Why is `modelPropertyType` off by default?

Because it is only as good as the resolved column list, and a gap in that list
shows up as a false positive rather than as silence. Turning it on for a project
whose migrations are not fully visible produces a wall of errors about columns
that exist.

Point the path options at the right places and confirm your properties resolve
first. If `$user->email` already gives `string`, the scan found your table and
the check has something accurate to work from.

## Do I have to annotate my relations?

Yes, with generics. The related model is read from the relation method's
declared return type, not from the `hasMany(Post::class)` argument:

```php
/** @return HasMany<Post, $this> */
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

An undocumented relation resolves to the base relation class and the related
model is lost. See [relations](../guide/relations.md).

## Can I use it on a package?

Yes. Install `orchestra/testbench` as a dev dependency so there is an
application to boot, and set
[`configDirectories`](../reference/configuration.md#configdirectories) if your
package ships config files. See [analysing a
package](../getting-started/packages.md).

## My grouped collection annotation is rejected

`groupBy`, `keyBy` and `pluck` resolve keys precisely, so grouping by an enum's
value gives `Collection<10|20, ...>` rather than `Collection<int, ...>`. Since
`Collection` declares its templates invariantly, ask for the general type at the
annotation:

```php
/** @return Collection<covariant int, Collection<int, Item>> */
```

`array-key` works too, being a benevolent union. A plain `int|string` does not,
because it is matched invariantly and accepts neither side. See [precision, and
widening it where you want
it](../guide/collections.md#precision-and-widening-it-where-you-want-it).

## A collection is rejected when both sides read the same

Something like this, where the expected and the given type print identically:

```
Parameter #1 $values of method App\Repro::accept() expects
Illuminate\Support\Collection<int, string|null>,
Illuminate\Support\Collection<int, string|null> given.
    Tip: Template type TValue on class Illuminate\Support\Collection is not covariant.
```

The `Tip` is the whole message. The two types differ in a way the description
does not show, and because `TValue` is invariant, differing is enough to fail.
Ask for the general type at the annotation:

```php
/** @param Collection<int, covariant string|null> $values */
```

That is sound in a way that changing the collection is not: `covariant` at a
parameter promises only that you will read from it, and the parameter is the one
place that can keep the promise.

## Why is a collection's value type invariant?

Because a collection is not covariant, and the stubs say so. `Collection` has
`push()`, `put()`, `prepend()`, `add()` and `offsetSet()`, all of which *take* a
`TValue`, and `offsetSet()` comes from `ArrayAccess`, a PHP core interface. A
container you can write into cannot be covariant in what it holds.

The framework does declare `@template-covariant TValue`, which is why this reads
like something the extension took away. Nothing enforces that annotation against
the framework's own source, so it ships as an unverified claim rather than a
working one. Stub files *are* validated, so the same annotation fails the moment
it moves into a stub---and it fails as `generics.variance`, which cannot be
ignored or baselined. The stubs are not stricter by choice; a stub is simply the
first place the claim gets checked.

Use `covariant` at your annotations, as above.

## Why did a new release start reporting errors?

Because inference improved. That is not treated as a breaking change here, for
reasons set out in [backward compatibility](../about/backward-compatibility.md): a
static analyser that may never report anything new is one that may never
improve. It will not be a new rule, since those ship off by default. Commit
your `composer.lock` and you control when the improvements arrive.

## Do I need an SQL parser?

Only if you have squashed schema dumps under `database/schema`. Neither parser
is a hard dependency, so the license that enters your tree is your choice. See
[installation](../getting-started/installation.md#squashed-schema-dumps).

## Does a GPL parser affect my application's license?

No. Copyleft is triggered by distributing the code, and a dev-only analyser is
not linked into your application or shipped with it. `composer install --no-dev`
leaves it out entirely. [The full
note](../reference/configuration.md#a-note-on-the-gpl-20-parser) covers the
cases where it genuinely would matter.

## Can I extend the PHP classes?

They are not a public API. The classes in `src/` are implementation and change
freely in any release; the supported surface is the options, the error
identifiers and the documented PHPDoc types. If you need something from the
internals, open an issue describing the goal.

## Is this Larastan?

It began as a fork of it and carries the same analysis features, rules and
stubs. It has since diverged: see [differences from
Larastan](../about/differences-from-larastan.md) for what changed and
[the migration guide](../migrating-from-larastan.md) for how to switch.

Remove Larastan when you switch. With both installed the extension is
registered twice and every error is reported twice.

## Why is analysis slow?

The two scanning rules, [unused view](../rules/views-and-translations.md#unused-view)
and [missing translation](../rules/views-and-translations.md#missing-translation),
search your project and are the usual answer. Both are off by default.

Booting the application costs a fixed amount once per run. Migration and schema
scanning is proportional to how many files you have; if your models carry
`@property` annotations already and you do not need it, `scanMigrations: false`
and `scanSchema: false` skip it.

Beyond that, the usual PHPStan advice applies: keep the result cache between
runs, and do not point `paths` at `vendor`.
