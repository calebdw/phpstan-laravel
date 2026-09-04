# Agents

This is **phpstan-laravel** (`calebdw/phpstan-laravel`), a PHPStan extension
for Laravel. It started as a Larastan fork after years of PRs that never
landed, then became its own published package: own namespace
(`CalebDW\PhpstanLaravel`), own config surface, own release policy, and a
lot of inference Larastan does not have. Collection typing (pluck, keyBy,
groupBy, and friends) is the obvious example. Treat Larastan as ancestry,
not as a source of habits.

## Handle the general case

Do not code the happy path of one class, one constant string, one union
member. Larastan did that constantly (`getObjectClassNames()[0]`, first
constant string, first template) and it falls over the moment someone
passes `User|Post` or `'name'|'email'`.

- Walk every class, every constant, every union member.
- `TypeCombinator::union(...)` the results.
- Take `[0]` only after you have proven there is exactly one.
- `TrinaryLogic` is three-valued. `yes()` / `no()` / `maybe()` — do not
  collapse maybe into no.
- Prefer PHPStan's type API (`isArray()`, `isString()`, `hasMethod()`) over
  `instanceof SomeType`.

A union handled member-by-member works for a single type for free. The
reverse is not true.

## Helpers, not copies

Logic that more than one extension needs lives in `src/Support/`. Use it.

| Helper | Use it for |
| --- | --- |
| `ColumnHelper` | Column / callback / dotted path resolution |
| `CollectionHelper` | `generic()`, `toBase()`, model collection class |
| `TypeHelper` | `isCalledOn`, constant strings, hasMethod/hasProperty |
| `ModelHelper` | Instantiated model, key type |
| `SelectHelper` | `Arr::select` / `Collection::select` shapes |


Keep methods short. If an extension is growing a second copy of normalize /
generic / column lookup, it belongs on a helper.

## Stubs vs extensions

Stubs overlay signatures PHPStan cannot see, or that Laravel's phpdoc gets
wrong. Extensions exist when the return type depends on the arguments
(`pluck('name')` vs `pluck('id')`) or on the receiver's templates. Do not
stuff argument-dependent types into a stub.

## Tests

Every inference change needs `assertType` coverage under `tests/Type/data/`,
registered in `GeneralTypeTest`. Prefer a dedicated file for a method family
(`collection-map-to-groups.php`)

Run the relevant type tests, not the whole suite:

```
vendor/bin/phpunit tests/Type/GeneralTypeTest.php --filter collection-map-to-groups
```

`phpcbf` and `composer test:types` (`phpstan analyse src`) after
PHP changes. Doctrine Coding Standard

## Closures

Inline closures passed straight into a call stay small. PHPStan already
knows the parameter types from the callee.

```php
// yes
$users->map(fn ($u) => $u->email);
$users->mapToGroups(fn ($u) => [$u->email => $u]);

// no — this is a one-liner that became a paragraph
$users->map(fn (User $u): string => $u->email);
```

- Single-letter parameter names (`$u`, `$t`, `$v`).
- No parameter types, no return type.
- One expression, one line.

Add names, types, and a return type when the closure is assigned, reused, or
actually multi-line.

## Style

Doctrine CS. Assignment alignment. Files end with a newline. No narrating
comments (`// increment i`). A comment that explains a runtime quirk PHPStan
cannot see is welcome — `ColumnHelper::normalizeKey` is the model.

Match surrounding code. Do not introduce a library this repo does not
already use.
