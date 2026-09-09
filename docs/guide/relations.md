# Relations

Relationship types are read from the relation method's declared return type, so
that type has to carry its generic parameters. The class the relation points at
is taken from there rather than from the `hasMany(Post::class)` argument, which
means an undocumented relation resolves to the base relation class and the
related model is lost.

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
`with()` include the relation object as well. See
[`builder-of`](custom-types.md#builder-of) for the `builder-of<User, 'posts'>`
phpdoc form.
