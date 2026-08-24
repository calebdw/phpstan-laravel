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

Where migrations live somewhere unconventional, or a table is built in a way the
scanner cannot follow, point the
[path options](../reference/configuration.md#migrationdirectories) at the right
directories. Enabling
[`modelPropertyType`](../reference/configuration.md#modelpropertytype) then
checks property *names* passed to methods, catching typos in things like
`User::create([...])`.

## Checking property names

This one is not a rule. Enabling the option activates the
[`model-property`](../guide/custom-types.md) type, and the mismatches are then reported
by PHPStan's ordinary argument checks, which is why the errors carry core
identifiers rather than a `laravel.*` one.

Every argument typed `model-property` is checked against the model's columns,
and an argument naming a column the model does not have is reported.

### Configuration

```neon
parameters:
    laravel:
        modelPropertyType: true
```

Whether it is accurate depends on how completely your columns were resolved.
Where migrations or schema dumps are missing, or a table is built in a way the
scanner cannot follow, the gap surfaces as a false positive rather than as
silence, which is why it is off by default. Point
[`migrationDirectories`](../reference/configuration.md#migrationdirectories) and
[`schemaDirectories`](../reference/configuration.md#schemadirectories) at the
right places before enabling it.

### Basic example

```php
User::create([
    'name' => 'John Doe',
    'emaiil' => 'john@example.test'
]);
```

Here we have a typo in `email` column. So if we run analysis on this file this extension will generate the following error:

```
Property 'emaiil' does not exist in App\User model.
```

This check will be done automatically on Laravel's core methods where a property is expected. But you can also typehint the `model-property` in your own code to take advantage of this analysis.

You can define a function like this:
```php
/**
 * @phpstan-param model-property<\App\User> $property
 */
function takesOnlyUserModelProperties(string $property)
{
    // ...
}
```

And if you call the function above with a property that does not exist in User model, this extension will warn you about it.

```php
// Property 'emaiil' does not exist in App\User model.
takesOnlyUserModelProperties('emaiil');
```

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
