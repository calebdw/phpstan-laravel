# Rules

Rules for mistakes that Laravel will happily let you make at runtime, grouped
below by what they cover and listed by default at the end.

Each rule is documented with its error identifier, the option that switches it
on or off, and its default. Toggles live under `laravel.rules` and are named
after what the rule reports, so `rules.modelMake` is the switch for
`laravel.modelMake`:

```neon
parameters:
    laravel:
        rules:
            modelMake: false
            unusedView: true
```

## On by default

| Rule | Identifier |
| --- | --- |
| [Model make](eloquent.md#model-make) | `laravel.modelMake` |
| [Model appends](eloquent.md#model-appends) | `laravel.modelAppends` |
| [Unnecessary collection call](collections.md#unnecessary-collection-call) | `laravel.unnecessaryCollectionCall` |
| [Unnecessary enumerable toArray call](collections.md#unnecessary-enumerable-toarray-call) | `laravel.unnecessaryEnumerableToArrayCall` |
| [Config accessor](config.md#config-accessor) | `laravel.configAccessor` |
| [Env call outside config](config.md#env-call-outside-config) | `laravel.envCallOutsideConfig` |

## Always enabled

These report unconditionally. There is no option to switch them off, because
each one reports something that is a bug in every codebase rather than a
matter of taste. Ignore by identifier if you need an exception.

| Rule | Identifier |
| --- | --- |
| [Relation existence](eloquent.md#relation-existence) | `laravel.relationExistence` |
| [Dispatch argument types](framework.md#dispatch-argument-types) | `laravel.jobs.noConstructor`, `laravel.events.noConstructor` |
| [Undefined console argument or option](framework.md#undefined-console-argument-or-option) | `laravel.console.undefinedArgument`, `laravel.console.undefinedOption` |
| [Deferrable service provider without provides()](framework.md#deferrable-service-provider-without-provides) | `laravel.deferrableServiceProvider.missingProvides` |
| [Useless value() call](framework.md#useless-value-call) | `laravel.uselessConstructs.value` |
| [Useless with() call](framework.md#useless-with-call) | `laravel.uselessConstructs.with` |

## Off by default

Off for one of two reasons: the rule enforces a style choice rather than
finding a bug, or it depends on a scan that can be incomplete, in which case
the gap shows up as a false positive rather than as silence.

| Rule | Identifier | Off because |
| --- | --- | --- |
| [Model method visibility](eloquent.md#model-method-visibility) | `laravel.modelMethodVisibility.scope`, `….accessor` | style |
| [Model forwarding to builder](eloquent.md#model-forwarding-to-builder) | `laravel.modelForwardingToBuilder` | style |
| [Model static forwarding to builder](eloquent.md#model-static-forwarding-to-builder) | `laravel.modelStaticForwardingToBuilder` | style |
| [Octane compatibility](framework.md#octane-compatibility) | `laravel.octaneCompatibility` | only applies under Octane |
| [Auth in request scope](framework.md#auth-in-request-scope) | `laravel.authInRequestScope.facade`, `….helper` | style |
| [Unused view](views-and-translations.md#unused-view) | `laravel.unusedView` | scan can be incomplete |
| [Missing translation](views-and-translations.md#missing-translation) | `laravel.missingTranslation` | scan can be incomplete |

## Not a rule

[Checking column names](../guide/model-properties.md#checking-property-names)
looks like a rule and is often described as one, but it is a type. Enabling
[`modelPropertyType`](../reference/configuration.md#modelpropertytype)
activates `model-property<Model>`, after which PHPStan's ordinary argument
checks do the reporting, so the errors carry core identifiers such as
`argument.type` rather than a `laravel.` one.


## Ignoring

Prefer ignoring [by identifier][identifiers] over ignoring by message or by
line. Identifiers are covered by the [backward compatibility
policy](../about/backward-compatibility.md) and message wording is not, so a
message-based ignore can stop matching in any release.

```neon
parameters:
    ignoreErrors:
        - identifier: laravel.modelMake
```

[Every identifier is listed here](../reference/identifiers.md).

<!-- links -->
[identifiers]: https://phpstan.org/user-guide/ignoring-errors#ignoring-by-identifier
