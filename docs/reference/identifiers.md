# Error identifiers

Every error this extension reports carries an identifier prefixed `laravel.`.
Identifiers are part of the [supported
surface](../about/backward-compatibility.md): they are what you ignore against,
so they are not renamed outside a major release. Message wording is not covered
and may be reworded at any time, which is why ignoring by identifier is always
preferable to ignoring by message.

```neon
parameters:
    ignoreErrors:
        - identifier: laravel.modelMake
```

An identifier names what was found, not the policy behind it, and never carries
a `no` prefix. Where one concern reports more than one kind of error, the
identifiers share a stem: `laravel.modelMethodVisibility.scope` and
`laravel.modelMethodVisibility.accessor`.

## Every identifier

| Identifier | Reported when | Option |
| --- | --- | --- |
| [`laravel.authInRequestScope.facade`](../rules/framework.md#auth-in-request-scope) | `Auth::user()` is used where a `$request` is already in scope | `rules.authInRequestScope` |
| [`laravel.authInRequestScope.helper`](../rules/framework.md#auth-in-request-scope) | `auth()->user()` is used where a `$request` is already in scope | `rules.authInRequestScope` |
| [`laravel.configAccessor`](../rules/config.md#config-accessor) | A typed config accessor is called for a key of another type | `rules.configAccessor` |
| [`laravel.console.undefinedArgument`](../rules/framework.md#undefined-console-argument-or-option) | A command reads an argument its signature does not define | always |
| [`laravel.console.undefinedOption`](../rules/framework.md#undefined-console-argument-or-option) | A command reads an option its signature does not define | always |
| [`laravel.deferrableServiceProvider.missingProvides`](../rules/framework.md#deferrable-service-provider-without-provides) | A deferrable provider does not implement `provides()` | always |
| [`laravel.envCallOutsideConfig`](../rules/config.md#env-call-outside-config) | `env()` is called outside the config directory | `rules.envCallOutsideConfig` |
| [`laravel.events.noConstructor`](../rules/framework.md#dispatch-argument-types) | An event with no constructor is dispatched with arguments | always |
| [`laravel.jobs.noConstructor`](../rules/framework.md#dispatch-argument-types) | A job with no constructor is dispatched with arguments | always |
| [`laravel.missingTranslation`](../rules/views-and-translations.md#missing-translation) | A translation key has no translation | `rules.missingTranslation` |
| [`laravel.modelAppends`](../rules/eloquent.md#model-appends) | `$appends` names something that is not a computed property | `rules.modelAppends` |
| [`laravel.modelForwardingToBuilder`](../rules/eloquent.md#model-forwarding-to-builder) | A builder method is called on a model instance | `rules.modelForwardingToBuilder` |
| [`laravel.modelMake`](../rules/eloquent.md#model-make) | `Model::make()` is called instead of `new Model()` | `rules.modelMake` |
| [`laravel.modelMethodVisibility.accessor`](../rules/eloquent.md#model-method-visibility) | An attribute accessor is `public` | `rules.modelMethodVisibility` |
| [`laravel.modelMethodVisibility.scope`](../rules/eloquent.md#model-method-visibility) | A local query scope is `public` | `rules.modelMethodVisibility` |
| [`laravel.modelStaticForwardingToBuilder`](../rules/eloquent.md#model-static-forwarding-to-builder) | A builder method is called statically on a model | `rules.modelStaticForwardingToBuilder` |
| [`laravel.octaneCompatibility`](../rules/framework.md#octane-compatibility) | A binding captures the container in a way Octane cannot reuse | `rules.octaneCompatibility` |
| [`laravel.relationExistence`](../rules/eloquent.md#relation-existence) | A builder method names a relation that does not exist | always |
| [`laravel.undefinedConfigName`](../rules/config.md#undefined-config-name) | A named Laravel service is not defined in its configuration | `rules.undefinedConfigName` |
| [`laravel.unnecessaryCollectionCall`](../rules/collections.md#unnecessary-collection-call) | A collection method could have been a query instead | `rules.unnecessaryCollectionCall.enabled` |
| [`laravel.unnecessaryEnumerableToArrayCall`](../rules/collections.md#unnecessary-enumerable-toarray-call) | `toArray()` is called where `all()` would do the same | `rules.unnecessaryEnumerableToArrayCall` |
| [`laravel.unusedView`](../rules/views-and-translations.md#unused-view) | A Blade view is never referenced | `rules.unusedView` |
| [`laravel.uselessConstructs.value`](../rules/framework.md#useless-value-call) | `value()` is called with no closure | always |
| [`laravel.uselessConstructs.with`](../rules/framework.md#useless-with-call) | `with()` returns its argument unchanged | always |

## Errors without a `laravel.` identifier

Some of what this extension does produces errors under PHPStan's own
identifiers, because the extension supplies better type information and
PHPStan's ordinary checks then do the reporting. These cannot be ignored
separately from the same check elsewhere in your code:

| What | Identifier |
| --- | --- |
| A column name that does not exist, via [`modelPropertyType`](configuration.md#modelpropertytype) | `argument.type` |
| A Blade view that does not exist, via `view-string` | `argument.type` |
| A property that is not a resolved column or accessor | `property.notFound` |
| Too few or too many arguments to a dispatched job or event | `arguments.count` |
| A dispatched argument of the wrong type | `argument.type` |

The last two are worth knowing about. [Dispatch argument
types](../rules/framework.md#dispatch-argument-types) delegates to PHPStan's own
argument checking, so only its no-constructor case gets a `laravel.` identifier.
The message still names the job and the constructor, so it is recognisable in
output, but you cannot ignore mismatched dispatch arguments by identifier
without also ignoring every other argument mismatch in your code.
