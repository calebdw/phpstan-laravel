# Troubleshooting

Parts of Laravel remain genuinely too dynamic to analyse. This page collects
the known limits and what to do about each. If your problem is a property or
relation that is not resolving, start with the [FAQ](faq.md) instead.

## Ignoring what cannot be fixed

Where a limit is real, ignore it [by identifier][identifiers] rather than by
message or by line. Identifiers are covered by the [backward compatibility
policy](../about/backward-compatibility.md) and message wording is not, so a
message-based ignore can silently stop matching in any release and start
failing your build.

```neon
parameters:
    ignoreErrors:
        - identifier: laravel.modelMake
```

Set [`reportUnmatchedIgnoredErrors`][unmatched] to `true` so that an ignore
which no longer matches is reported rather than left to rot. The cost is that a
release which *fixes* a false positive fails your build until you delete the
now-unused entry, which is the right trade in most projects.

## Higher order messages on a support collection

The [higher order proxy](https://laravel.com/docs/collections#higher-order-messages)
is understood, but only as far as the collection's value type is known. On an
`Eloquent\Collection` that is a model and everything resolves. On a plain
`Support\Collection` whose values are `mixed`, the proxy has nothing to look the
method up on:

```neon
parameters:
    ignoreErrors:
        - '#Call to an undefined method Illuminate\\Support\\HigherOrder#'
```

The better fix is to give the collection a value type, which resolves it
properly rather than hiding it:

```php
/** @var Collection<int, User> $users */
$users->groupBy->email; // Collection<string, Collection<int, User>>
```

## Macros

Macros registered at runtime are found by booting the application, so a macro
registered in a service provider is understood. One registered somewhere that
does not run during boot, such as inside a test helper or behind a conditional,
is not, and calls to it are reported as undefined methods.

Declaring the macro on the class with `@method` is the fix, since it documents
the API for humans and editors at the same time.

## Models without resolvable columns

A model whose table this extension cannot see, because the table belongs to
another service or is created outside migrations, will have every column
reported as missing once
[`checkModelProperties`](../reference/configuration.md#checkmodelproperties) is
on. Annotate those models with `@property` and they behave normally.

## Analysis is quiet on a package

If no application is found and Testbench is not installed, analysis runs but
every container lookup returns nothing, so config types, `app()` resolution and
view names all fall back to their widest types. Nothing errors, which makes it
look like the extension is doing very little. See [analysing a
package](../getting-started/packages.md).

## The run fails while booting

Booting is fatal by design: nothing useful can be analysed once a service
provider has failed, so the underlying exception is reported rather than a wall
of unrelated errors. The message points at the real failure, which is usually a
missing environment variable or a provider that expects a service the analysis
environment does not have.

## A schema dump cannot be parsed

An unreadable dump fails the run rather than being skipped, because the tables
it defines would otherwise go missing from your model properties with nothing
to say so. Options, in order of preference: fix the dump, choose the other
parser with [`sqlParser`](../reference/configuration.md#sqlparser), or set
`scanSchema: false` to opt out of dumps entirely.

Both parsers focus on the MySQL dialect. PostgreSQL dumps parse only in plain
text format, and even then problems have been reported with timestamp columns
and long parse times. For PostgreSQL, generating migrations with
[laravel-migrations-generator][gen] or writing `@property` annotations with
[laravel-ide-helper][ide] are the more reliable routes.

<!-- links -->
[identifiers]: https://phpstan.org/user-guide/ignoring-errors#ignoring-by-identifier
[unmatched]: https://phpstan.org/user-guide/ignoring-errors#reporting-unused-ignores
[gen]: https://github.com/kitloong/laravel-migrations-generator
[ide]: https://github.com/barryvdh/laravel-ide-helper
