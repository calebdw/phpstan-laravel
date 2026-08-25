# Model properties

Your migrations and schema dumps are scanned to work out each table's columns,
which is how the magic properties on an Eloquent model get their types. Columns
are tracked per connection, so a model on a second connection resolves against
that connection's tables.

```php
$user->email;      // string
$user->emial;      // Access to an undefined property App\User::$emial
```

A nullable column produces a nullable type, and a date column resolves to
whichever Carbon class your application is configured to use, so a nullable
`created_at` is `Carbon\Carbon|null` rather than a bare `Carbon`.

The rest of a model's metadata---casts, appends, dates, key type, incrementing,
fillable, table name---is read from an instantiated model rather than from the
class, so Laravel's own boot sequence runs and anything a trait contributes is
visible. A cast registered by a trait in a third-party package is understood for
the same reason `HasUlids` is.

## Laravel IDE Helper

phpstan-laravel already derives model properties from your schema, casts and
accessors. Do not use [Laravel IDE Helper][ide-helper] to write generated
`@property` annotations onto your models, such as with
`ide-helper:models --write`. An explicit property annotation takes precedence
over the extension's inference, even when it was generated rather than written
by hand. The extension cannot distinguish between the two or know which parts
of the declared type were intended to override Laravel.

You can still use IDE Helper for facade completion and PhpStorm metadata with
`ide-helper:generate` and `ide-helper:meta`. If you generate model metadata with
`ide-helper:models --nowrite`, keep `_ide_helper_models.php` available to your
IDE but outside PHPStan's analysed paths, bootstrap files and autoloading. Avoid
`--write-mixin` as well when its generated model helper is visible to PHPStan,
because properties supplied through a mixin are also treated as explicit
overrides.

Add model `@property` annotations only where you deliberately want to replace
the inferred type, for example when a model is backed by a view or external
table the schema scanner cannot see.

Where migrations live somewhere unconventional, or a table is built in a way the
scanner cannot follow, point the
[path options](../reference/configuration.md#migrationdirectories) at the right
directories. Enabling
[`modelPropertyType`](../reference/configuration.md#modelpropertytype) then
checks property *names* passed to methods, catching typos in things like
`User::create([...])`.

## Checking column names

Resolving the columns tells you the *type* of `$user->email`. Turning on
`modelPropertyType` also checks the column *names* you pass to methods, so a
typo is caught where it is written:

```neon
parameters:
    laravel:
        modelPropertyType: true
```

```php
User::create([
    'name' => 'John Doe',
    'emaiil' => 'john@example.test',
]);
// Property 'emaiil' does not exist in App\User model.
```

Laravel's own methods that expect a column are annotated for you, so this
applies across the query builder, relations and mass assignment without any
work. You can annotate your own the same way:

```php
/** @param model-property<\App\User> $column */
function sortBy(string $column): void
{
    // ...
}

sortBy('emaiil');  // Property 'emaiil' does not exist in App\User model.
```

It is a type rather than a rule, which is why it has no `laravel.` identifier:
the option activates [`model-property`](custom-types.md), and PHPStan's ordinary
argument checks do the reporting, under identifiers like `argument.type`.

How accurate it is depends on how completely your columns resolved. Where
migrations or schema dumps are missing, or a table is built in a way the scanner
cannot follow, the gap surfaces as a false positive rather than as silence,
which is why it is off by default. Confirm `$user->email` already resolves, and
point [`migrationDirectories`](../reference/configuration.md#migrationdirectories)
and [`schemaDirectories`](../reference/configuration.md#schemadirectories) at the
right places, before turning it on.

## Reading a subset of attributes

`only` builds a shape out of the attributes you name, resolved exactly the way
`$user->email` is. Columns, casts, both accessor styles and `@property`
annotations all carry their types through it:

```php
$user->only('name', 'email');                   // array{name: string, email: string}
$user->only(['blocked']);                       // array{blocked: bool}
$user->only(['only_available_with_accessor']);  // array{only_available_with_accessor: string}
```

An attribute the model does not have comes back as null rather than being
dropped, which is what `getAttribute()` does at runtime:

```php
$user->only(['name', 'nope']);  // array{name: string, nope: null}
```

A key that is not known while analysing leaves no shape to build, so the result
widens:

```php
$user->only(['name', $key]);  // array<string, mixed>
```

!!! warning "No dot notation"

    `getAttribute()` does not split on dots, so a dotted key is looked up whole
    and misses:

    ```php
    $user->only(['meta.a']);  // array{'meta.a': null}
    ```

    That is what the framework returns at runtime.
    [`Arr::only`](collections.md#only) has the same limitation, for its own
    reasons.

## Accessors and mutators

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
[ide-helper]: https://github.com/barryvdh/laravel-ide-helper#automatic-phpdocs-for-models
