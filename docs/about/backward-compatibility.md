# Backward Compatibility

This package follows [semantic versioning][semver], and takes the same view of
type inference that [PHPStan itself does][phpstan-bc]: as the analyser gets
smarter its output changes, and that is not a breaking change. That view is
worth stating plainly, because static analysis does not fit the usual shape of
a library.

This package goes one step further than PHPStan on one point. PHPStan adds new
rules only in major versions or behind [bleeding edge][bleeding-edge]; here a
new rule may land in a minor release. The reasoning is in the next section.

## New errors are not a breaking change

As bugs are fixed and inference improves, this extension understands your code
more precisely. More precision means errors that were previously missed start
being reported, and errors that were previously wrong stop being reported.
Either direction can fail a build that passed yesterday.

That is the nature of a static analyser, and it is not a breaking change.

A failing analysis is not a broken application. Nothing here runs in
production; it runs in CI, behind a lock file you control, against code you
have not shipped yet. The worst case is a build that asks you to look at
something. That is the job. An analyser that may never report anything new is
an analyser that may never improve, and refusing better inference because a
pipeline might go red trades the entire point of the tool for the appearance of
stability.

So a minor or patch release may:

- report errors it previously missed, because inference got better
- stop reporting errors that were false positives
- infer a narrower or wider type than it did before
- **add a new rule**, including one that is enabled by default

A new rule finds real problems in code that already had them. Withholding it
until a major version does not make anyone's application safer; it just delays
the news.

## What is a breaking change

Breaking changes are reserved for major versions, and mean changes that require
you to edit something for this package to keep working the way you configured
it:

- **Removing or renaming a configuration option**, which makes an existing
  config file invalid.
- **Changing what an existing option means.**
- **Renaming or removing an error identifier**, since identifiers are what you
  ignore against.
- **Raising the minimum PHP or Laravel version.**

Note what is not on that list: how many errors you get. A release that reports
more is doing what you installed it to do.

Enabling a check that was previously off does change what a build reports
without you touching anything, so it is called out prominently in the release
notes and avoided in patch releases. It is still not a breaking change by the
definition above, because nothing you wrote has stopped working.

## The PHP classes are not a public API

The classes in `src/` are implementation. They are not intended to be extended,
implemented, or instantiated from your own code, and they change freely in any
release. This is not a limitation peculiar to this package; a PHPStan extension
is configuration and inference rather than a library you build on.

The supported surface is:

- the options under `parameters.laravel` in your PHPStan configuration
- the error identifiers reported, which you may ignore against
- the custom PHPDoc types documented in [custom types](../guide/custom-types.md)

If you need something from the internals, open an issue describing the goal
rather than reaching into a class.

## Keeping your build stable

If you would rather adopt improvements deliberately, the same advice PHPStan
gives applies here:

- Commit your `composer.lock`. With a caret constraint you get improvements
  when you run `composer update`, and nothing changes underneath you until you
  do.
- Set [`reportUnmatchedIgnoredErrors`][unmatched] to `false` if you do not want
  a release that *fixes* a false positive to fail your build over a now-unused
  ignore.
- If you value stability above all, pin an exact version and let Dependabot or
  similar propose upgrades on its own schedule.

Prefer ignoring errors [by identifier][identifiers] over ignoring by message or
by line. Identifiers are covered by this policy; the exact wording of a message
is not, and may be reworded in any release to read more clearly.

<!-- links -->
[semver]: https://semver.org
[phpstan-bc]: https://phpstan.org/user-guide/backward-compatibility-promise
[bleeding-edge]: https://phpstan.org/blog/what-is-bleeding-edge
[unmatched]: https://phpstan.org/user-guide/ignoring-errors#reporting-unused-ignores
[identifiers]: https://phpstan.org/user-guide/ignoring-errors#ignoring-by-identifier
