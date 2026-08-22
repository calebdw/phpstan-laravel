# CONTRIBUTING

Contributions are welcome, and are accepted via pull requests.
Please review these guidelines before submitting any pull requests.

## Process

1. Fork the project
1. Create a new branch
1. Code, test, commit and push
1. Open a pull request detailing your changes. Make sure to follow the [template](.github/PULL_REQUEST_TEMPLATE.md)

## Guidelines

* Please follow the coding standards enforced by [Doctrine Coding Standard](https://github.com/doctrine/coding-standard/). You can check the code style by running `composer test:cs` and automatically format it using `./vendor/bin/phpcbf`.
* Send a coherent commit history, making sure each individual commit in your pull request is meaningful.
* You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
* Please remember that we follow [SemVer](http://semver.org).

## Setup

Clone your fork, then install all dependencies:

    composer update

## Tests

Run code style checks:

    composer test:cs

Run all tests:

    composer test

Code analysis:

    composer test:types

Unit tests:

    composer test:unit

### Analysing real projects

The checks above are what you normally need. The following analyse whole
applications with the extension installed from source, which is useful when
debugging a change against real-world code. Both are also what CI runs, so they
behave identically here.

Analyse a freshly installed Laravel application:

    composer test:application

Analyse a real-world project, pinned to a known commit:

    composer test:e2e monicahq-monica
    composer test:e2e filamentphp-filament

Projects are created under `build/` and reused between runs. Pass `--fresh` to
recreate one from scratch, and set `PHPSTAN_MEMORY_LIMIT` to override the
default memory limit:

    composer test:application -- --fresh
