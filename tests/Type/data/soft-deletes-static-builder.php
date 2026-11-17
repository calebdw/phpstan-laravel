<?php

namespace SoftDeletesStaticBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use function PHPStan\Testing\assertType;

/**
 * @template TModelClass of Model
 * @extends Builder<TModelClass>
 */
class RenamedBuilder extends Builder {}

/** @extends Builder<Post> */
class FixedBuilder extends Builder {}

/**
 * @template TPost of Post
 * @extends RenamedBuilder<TPost>
 */
class InheritedBuilder extends RenamedBuilder {}

class Post extends Model
{
    use SoftDeletes;

    public static function forwardedCreation(): void
    {
        assertType('static(SoftDeletesStaticBuilder\Post)', static::restoreOrCreate(['id' => 1]));
        assertType('static(SoftDeletesStaticBuilder\Post)', self::createOrRestore(['id' => 1]));
    }

    /** @param Builder<static> $query */
    public function staticBuilder(Builder $query): void
    {
        assertType('Illuminate\Database\Eloquent\Builder<static(SoftDeletesStaticBuilder\Post)>', $query->onlyTrashed());
        assertType('Illuminate\Database\Eloquent\Builder<static(SoftDeletesStaticBuilder\Post)>', $query->withTrashed());
        assertType('Illuminate\Database\Eloquent\Builder<static(SoftDeletesStaticBuilder\Post)>', $query->withoutTrashed());
        assertType('Illuminate\Database\Eloquent\Builder<static(SoftDeletesStaticBuilder\Post)>', $query->onlyTrashed()->where('id', 1));
        assertType('static(SoftDeletesStaticBuilder\Post)', $query->restoreOrCreate(['id' => 1]));
        assertType('static(SoftDeletesStaticBuilder\Post)', $query->createOrRestore(['id' => 1]));
        assertType('int', $query->restore());
    }

    /** @param Builder<$this> $query */
    public function thisBuilder(Builder $query): void
    {
        assertType('Illuminate\Database\Eloquent\Builder<$this(SoftDeletesStaticBuilder\Post)>', $query->onlyTrashed());
        assertType('$this(SoftDeletesStaticBuilder\Post)', $query->restoreOrCreate(['id' => 1]));
    }

    /** @param RenamedBuilder<static> $query */
    public function renamedBuilder(RenamedBuilder $query): void
    {
        assertType('SoftDeletesStaticBuilder\RenamedBuilder<static(SoftDeletesStaticBuilder\Post)>', $query->onlyTrashed());
        assertType('static(SoftDeletesStaticBuilder\Post)', $query->restoreOrCreate(['id' => 1]));
    }

    /** @param InheritedBuilder<static> $query */
    public function inheritedBuilder(InheritedBuilder $query): void
    {
        assertType('static(SoftDeletesStaticBuilder\Post)', $query->restoreOrCreate(['id' => 1]));
        assertType('static(SoftDeletesStaticBuilder\Post)', $query->createOrRestore(['id' => 1]));
    }
}

function concreteModelCalls(Post $model): void
{
    assertType('SoftDeletesStaticBuilder\Post', Post::restoreOrCreate(['id' => 1]));
    assertType('SoftDeletesStaticBuilder\Post', Post::createOrRestore(['id' => 1]));
    assertType('SoftDeletesStaticBuilder\Post', $model->restoreOrCreate(['id' => 1]));
    assertType('SoftDeletesStaticBuilder\Post', $model->createOrRestore(['id' => 1]));
}

function fixedBuilder(FixedBuilder $query): void
{
    assertType('SoftDeletesStaticBuilder\Post', $query->restoreOrCreate(['id' => 1]));
    assertType('SoftDeletesStaticBuilder\Post', $query->createOrRestore(['id' => 1]));
}

/**
 * @template TPost of Post
 * @param Builder<TPost> $query
 */
function templateBuilder(Builder $query): void
{
    assertType('Illuminate\Database\Eloquent\Builder<TPost of SoftDeletesStaticBuilder\Post (function SoftDeletesStaticBuilder\templateBuilder(), argument)>', $query->onlyTrashed());
    assertType('TPost of SoftDeletesStaticBuilder\Post (function SoftDeletesStaticBuilder\templateBuilder(), argument)', $query->restoreOrCreate(['id' => 1]));
}
