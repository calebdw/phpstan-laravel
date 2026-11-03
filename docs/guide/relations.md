# Relations

Relationship types are read from the relation method's declared return type, so
that type has to carry its generic parameters. The class the relation points at
is taken from there rather than from the `hasMany(Post::class)` argument, which
means an undocumented relation resolves to the base relation class and the
related model is lost. Relation query callbacks and `builder-of` paths then use
`Builder<Model>` rather than assuming the relation still targets the declaring
model.

```php
/** @return BelongsTo<User, $this> */
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

/** @return HasMany<Post, $this> */
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

`whereHas`, `whereRelation`, and the morph variants type the query callback as
the related model's builder, including custom builders. `withWhereHas` and
`with()` include the relation object as well, and each closure in a builder
`with()` array receives the relation its key names
([`eager-load-of`](custom-types.md#eager-load-of)). See
[`builder-of`](custom-types.md#builder-of) for the `builder-of<User, 'posts'>`
phpdoc form.

## Pivot accessors

`BelongsToMany` and `MorphToMany` results carry their pivot model, including a
custom accessor named with `as()`. Declare the pivot class and accessor as the
third and fourth relation templates:

```php
/** @return BelongsToMany<Podcast, $this, Subscription, 'subscription'> */
public function podcasts(): BelongsToMany
{
    return $this->belongsToMany(Podcast::class)
        ->using(Subscription::class)
        ->as('subscription');
}

$member->podcasts()->firstOrFail()->subscription;
$member->podcasts->firstOrFail()->subscription;
```

Both accessor expressions have type `Subscription`. The pivot shape is
preserved in collection results, including custom model
collections, as well as paginated and lazy results. Collection value templates
are invariant: a collection of `Podcast&object{subscription: Subscription}`
is not interchangeable with a collection declared as just `Podcast`.

If the accessor is an unknown string or a union of names, no single pivot
property is guaranteed.
