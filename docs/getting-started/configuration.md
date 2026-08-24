# Configuration

Every option this extension defines lives under `parameters.laravel`, and the
options that switch a rule on or off live one level deeper under
`parameters.laravel.rules`:

```neon
parameters:
    level: 6
    paths:
        - app

    laravel:
        checkModelProperties: true
        viewDirectories:
            - resources/views
        rules:
            unusedView: true
```

PHPStan's own parameters stay where they are. The nesting keeps the two apart,
and keeps "switch this rule on" visibly separate from "here is where my
migrations live".

The whole tree is schema validated, so a misspelled or misplaced option fails
the run rather than being quietly ignored:

```
Invalid configuration:
Unexpected item 'parameters › laravel › rules › modelMakeTypo',
did you mean 'modelMake'?
```

## The one option worth turning on

`checkModelProperties` is the highest-value option and the one people most
often never notice. It checks arguments that name a column against the columns
resolved from your migrations, which catches this throughout Laravel's own
methods with no annotations of your own:

```php
User::create(['name' => 'John', 'emaiil' => 'john@example.test']);
// Property 'emaiil' does not exist in App\User model.
```

```neon
parameters:
    laravel:
        checkModelProperties: true
```

It is off by default for one reason: it is only as accurate as that resolved
column list. If migrations live somewhere unusual, or a table is built in a way
the scanner cannot follow, the result is false positives rather than silence.
Point [`migrationDirectories`](../reference/configuration.md#migrationdirectories)
and [`schemaDirectories`](../reference/configuration.md#schemadirectories) at
the right places first.

It is not a rule, which is why it sits directly under `laravel:`. It activates
the [`model-property`](../guide/custom-types.md) type, and the mismatches are
then reported by PHPStan's ordinary argument checks.

## Rules

Rule toggles are named after what the rule reports, matching the identifier it
produces: `rules.modelMake` switches off the rule behind `laravel.modelMake`.

```neon
parameters:
    laravel:
        rules:
            modelMake: false
            unusedView: true
```

[Rules](../rules/index.md) lists every rule with its default.

## Ignoring errors

Every error this extension reports carries a `laravel.` prefixed
[identifier][identifiers]. Prefer ignoring by identifier over ignoring by
message or by line: identifiers are covered by the [backward compatibility
policy](../about/backward-compatibility.md), message wording is not, and an
identifier does not suppress unrelated errors that happen to share a line.

```neon
parameters:
    ignoreErrors:
        - identifier: laravel.modelMake
```

[The identifier reference](../reference/identifiers.md) lists every one.

Switching a rule off and ignoring its identifier are not the same thing: a rule
that is off costs nothing to run, while an ignored one still runs and then has
its findings discarded. Prefer the option when you never want the check, and
the ignore when you want it everywhere except a few places.

## Directories

Five options tell the extension where to look. All accept absolute paths or
paths relative to the PHPStan config file that declares them:

| Option | Default |
| --- | --- |
| [`migrationDirectories`](../reference/configuration.md#migrationdirectories) | `database/migrations` |
| [`schemaDirectories`](../reference/configuration.md#schemadirectories) | `database/schema` |
| [`configDirectories`](../reference/configuration.md#configdirectories) | none, container only |
| [`viewDirectories`](../reference/configuration.md#viewdirectories) | Laravel's view finder |
| [`translationDirectories`](../reference/configuration.md#translationdirectories) | `lang_path()` |

Migration and schema paths expand `glob` wildcards, which modular applications
need:

```neon
parameters:
    laravel:
        migrationDirectories:
            - modules/*/database/migrations
```

## Next

- [The full option reference](../reference/configuration.md).
- [Rules](../rules/index.md), each with its identifier and default.

<!-- links -->
[identifiers]: https://phpstan.org/user-guide/ignoring-errors#ignoring-by-identifier
