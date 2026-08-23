@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
## PHPStan Laravel

- `calebdw/phpstan-laravel` teaches PHPStan about Laravel. Run `{{ $assist->binCommand('phpstan') }} analyse` and resolve what it reports before finalizing changes.
- Fix the code rather than silencing the report. Do not add `@phpstan-ignore` comments, baseline entries, `assert()` calls, inline `@var` tags, casts or widened signatures to make an error go away without approval.
@if ($assist->hasSkillsEnabled())
- Use the `phpstan-laravel-analysis` skill when configuring the extension, interpreting an error, or working with model property inference.
@else
- Extension options nest under `parameters.laravel` in `phpstan.neon`, and every error carries a `laravel.` prefixed identifier to ignore precisely when one is genuinely warranted.
@endif
