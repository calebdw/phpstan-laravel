# View and translation rules

Both rules search your project for references, so both are off by default:
they cost time to run, and a reference the scan cannot see looks exactly like
one that was never written.

## Unused view

`laravel.unusedView` &middot; option `rules.unusedView` &middot; off by default

Finds views in your application that are never used.

> **NOTE**: Due to the nature of static analysis, this rule can produce false positives. It cannot find every usage of a view, so it is possible that a view is reported as unused when it is actually used. This is why it's an optional rule.

### Configuration

```neon
parameters:
    laravel:
        rules:
            unusedView: true
```

Blade files under `resources/views` are scanned by default. Use
[`viewDirectories`](../reference/configuration.md#viewdirectories) for views kept
elsewhere:

```neon
parameters:
    laravel:
        rules:
            unusedView: true
        viewDirectories:
            - domainA/resources/views
            - a/path/to/views
```

### Supported View Usages

- `view` helper function.
- `$this->markdown` and `$this->view` methods in Mailables.
- `Illuminate\View\Factory::make` method.
- `Illuminate\Support\Facades\View::make` method.
- `Illuminate\Support\Facades\Route::view` method.
- `@extends` Blade directive.
- `@include` Blade directive.
- `@includeIf` Blade directive.
- `@includeUnless` Blade directive.
- `@includeWhen` Blade directive.
- `@includeFirst` Blade directive.

## Missing translation

`laravel.missingTranslation` &middot; option `rules.missingTranslation` &middot; off by default

Finds untranslated strings in your application. It is primarily meant for applications that make use of the dot syntax like `messages.greet`. If you're using translation strings as keys, this rule may be unnecessary. Enabling this rule may decrease performance as it will scan the available views and translations.

Translations from vendors like `vendor::key` will not be checked.

> **NOTE**: If you store your translations in a database, this rule will not be able to detect them. You should leave this rule disabled in such cases.

### Examples

For the following code:
```php
__('messages.greet')
```

This extension may report the following error:
```
Translation "messages.greet" has not been found.
```

### Configuration

```neon
parameters:
    laravel:
        rules:
            missingTranslation: true
```

`resources/lang` is scanned by default. If your translations live elsewhere,
register every path with
[`translationDirectories`](../reference/configuration.md#translationdirectories):

```neon
parameters:
    laravel:
        rules:
            missingTranslation: true
        translationDirectories:
            - resources/lang
            - resources/translations
```
