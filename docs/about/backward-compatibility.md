# Backward Compatibility

This package follows [semantic versioning][semver], and takes the same view of
type inference that [PHPStan itself does][phpstan-bc]: as the analyser gets
smarter its output changes, and that is not a breaking change. That view is
worth stating plainly, because static analysis does not fit the usual shape of
a library.

Where a release can change what your build reports, the line drawn here is
between inference and rules. Better inference ships in any release. New rules
ship switched off, so adding one changes nothing until you enable it.

## New errors are not a breaking change

As bugs are fixed and inference improves, this extension understands your code
more precisely. More precision means errors that were previously missed start
being reported, and errors that were previously wrong stop being reported.
Either direction can fail a build that passed yesterday.

That is the nature of a static analyser, and it is not a breaking change.

A failing analysis is not a broken application. Nothing here runs in
production; it runs in CI, behind a lock file you control, against code you
have not shipped yet. The worst case is a build that asks you to look at
something, which is what you installed it for. An analyser held to never
reporting anything new is one that can never improve.

So a minor or patch release may:

- report errors it previously missed, because inference got better
- stop reporting errors that were false positives
- infer a narrower or wider type than it did before

## New rules arrive switched off

PHPStan adds new rules only in major versions or behind [bleeding
edge][bleeding-edge]. Here a new rule may land in a minor release, but it
arrives off by default, behind an option under `laravel.rules` like every other
rule. Your build reports what it reported before until you choose to turn it
on.

Changing an existing rule's default from off to on is a different matter, since
that does change what an untouched configuration reports. It waits for a major
version, as does adding a rule to the small set that
[reports unconditionally](../rules/index.md#always-enabled).

The effect is that rules ship when they are ready and are adopted on your
schedule rather than on the release schedule.

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
- **Enabling by default a rule that was previously off**, since a configuration
  you have not touched then reports more.

Note what is not on that list: the errors better inference finds. A release
that understands your code more precisely is doing what you installed it to do.

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
  ignore. It defaults to `true`, which is the better trade in most projects
  because it stops stale ignores accumulating; turning it off buys quieter
  upgrades at that cost.
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
