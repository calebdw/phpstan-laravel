<?php

namespace RelationOfType;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

use function PHPStan\Testing\assertType;

/**
 * @param relation-of<\App\User, 'accounts'> $accounts
 * @param relation-of<\App\User, 'posts'> $posts
 * @param relation-of<\App\User, 'posts.comments'> $comments
 * @param relation-of<\App\User, 'syncableRelation'> $custom
 * @param relation-of<\App\User, 'posts:id'> $columns
 */
function test($accounts, $posts, $comments, $custom, $columns): void
{
    assertType('Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $accounts);
    assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>', $posts);
    assertType('Illuminate\Database\Eloquent\Relations\MorphMany<App\Comment, App\Post>', $comments);
    assertType('App\HasManySyncable<App\Account, App\User>', $custom);
    assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>', $columns);
}

/**
 * @param relation-of<\App\User, 'posts'|'accounts'> $names
 * @param relation-of<\App\User|\App\Post, 'posts'|'comments'> $models
 * @param relation-of<\App\User, 'posts'|'missing'> $partial
 * @param relation-of<\App\User, 'missing'> $missing
 * @param relation-of<\App\User, string> $unknown
 * @param relation-of<\App\User, 'posts'>|null $nullable
 */
function testUnions($names, $models, $partial, $missing, $unknown, $nullable): void
{
    assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>|Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $names);
    assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>|Illuminate\Database\Eloquent\Relations\MorphMany<App\Comment, App\Post>', $models);
    assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>', $partial);
    assertType('Illuminate\Database\Eloquent\Relations\Relation<Illuminate\Database\Eloquent\Model, App\User>', $missing);
    assertType('Illuminate\Database\Eloquent\Relations\Relation<Illuminate\Database\Eloquent\Model, App\User>', $unknown);
    assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>|null', $nullable);
}

/**
 * @template TModel of Model
 * @template TRelation of string
 * @param class-string<TModel> $model
 * @param TRelation $name
 * @return relation-of<TModel, TRelation>
 */
function genericRelation(string $model, string $name): Relation
{
    throw new \LogicException();
}

/** @param 'posts'|'missing' $name */
function testTemplates(string $name): void
{
    assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>', genericRelation(\App\User::class, 'posts'));
    assertType('Illuminate\Database\Eloquent\Relations\MorphMany<App\Comment, App\Post>', genericRelation(\App\User::class, 'posts.comments'));
    assertType('Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\User, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>', genericRelation(\App\User::class, $name));
}
