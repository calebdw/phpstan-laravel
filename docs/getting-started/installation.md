# Installation

## Requirements

| | Supported |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 12.67+ and 13.26+ |
| PHPStan | 2.2.2+ |

Only the two most recent Laravel releases are supported, and each at a recent
minor rather than the oldest release of that major. That keeps the codebase
free of version shims, and `composer update` will pull a supported minor for
you.

## Install the package

Install as a development dependency with [Composer][composer]:

```bash
composer require --dev calebdw/phpstan-laravel
```

If you use the [PHPStan extension installer][extension-installer] you are done.
Otherwise include the extension in your `phpstan.neon` (or `phpstan.neon.dist`):

```neon
includes:
    - vendor/calebdw/phpstan-laravel/extension.neon
```

Then analyse as usual:

```bash
vendor/bin/phpstan analyse
```

!!! tip "Coming from Larastan?"

    Remove Larastan first, then see [the migration
    guide](../migrating-from-larastan.md). With both installed the extension is
    registered twice and every error is reported twice.

## Squashed schema dumps

If your project has schema dumps under `database/schema`, you also need an SQL
parser. Neither is a hard requirement of this package, so the license that
enters your dependency tree is your choice rather than ours:

```bash
composer require --dev iamcal/sql-parser      # MIT
composer require --dev phpmyadmin/sql-parser  # GPL-2.0-or-later
```

Either works. `phpmyadmin/sql-parser` understands more of the MySQL dialect and
is preferred when both are installed. See
[`sqlParser`](../reference/configuration.md#sqlparser) to name one explicitly.

If you have no dumps you need neither: the parser is only resolved when there
is a dump to read.

!!! note "A GPL-2.0 package in `require-dev` does not affect your license"

    Copyleft is triggered by distributing the code. A development-only
    analyser is not linked into your application and is not shipped with it,
    and `composer install --no-dev` leaves it out of the tree entirely, so
    nothing you deploy contains any of it. The MIT option exists for projects
    with a blanket internal policy against GPL code, which is a policy
    constraint rather than a legal one. [The full
    note](../reference/configuration.md#a-note-on-the-gpl-20-parser) has the
    detail.

## Level

The extension works at every PHPStan level. Start at `0`, get to zero errors,
then raise the level by one and repeat:

```neon
parameters:
    level: 0
```

That is slower than jumping straight to a high level, and it is the reason it
works. The errors at the low levels are the ones your whole codebase rests on:
a base model, a service that returns `mixed`, an abstraction whose types were
never quite right. Fixing those first makes each later level cheaper, because
the types they introduce flow outward into everything built on top.

Skipping ahead inverts that. A first run at level 5 on an established
application can produce thousands of errors, and the only practical way to see
green is to baseline them. Now the structural problems are still there, just
recorded, and every pull request that touches those files has to regenerate the
baseline to get past CI. The team ends up maintaining a ledger of known
breakage instead of fixing the thing the ledger describes.

The checks that depend on knowing your columns pay off most from level 5 upward,
where argument types are verified. That is a reason to keep climbing, not a
reason to start there.

### On the baseline

A [baseline][baseline] is for the errors at your current level that are
genuinely not worth fixing right now, so that the level can be locked in and CI
stays honest about anything new:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

It is a holding position for a specific, understood set of errors, not a way to
adopt a level you have not actually reached. If generating one is the only way
to pass, the level is too high rather than the baseline too small.

## Next

- [Configuration](configuration.md) for the options and how they nest.
- [Analysing a package](packages.md) if there is no application to boot.

<!-- links -->
[baseline]: https://phpstan.org/user-guide/baseline
[composer]: https://getcomposer.org
[extension-installer]: https://phpstan.org/user-guide/extension-library#installing-extensions
