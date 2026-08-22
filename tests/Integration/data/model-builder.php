<?php

namespace ModelBuilder;

use App\Account;
use App\Post;
use App\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use function PHPStan\Testing\assertType;

class User extends Model
{
    /** @return Builder<static> */
    public static function testQueryStatic(): Builder
    {
        return static::query();
    }

    public static function testCreateStatic(): static
    {
        return static::query()->create();
    }

    public static function testCreateSelf(): self
    {
        return self::query()->create();
    }
}

function test(User|Account $userOrAccount): void
{
    \App\User::query()->where(DB::raw('1'), 1)->get();

    /** @see https://github.com/larastan/larastan/issues/1806 */
    \App\User::query()->orderBy(Post::query()->select('id')->whereColumn('user_id', 'users.id'));
    \App\User::query()->orderByDesc(Post::query()->select('id')->whereColumn('user_id', 'users.id'));

    \App\User::query()->get()->pluck('computed');

    /** @see https://github.com/larastan/larastan/issues/1952 */
    Team::query()->where('name', 'Team A')->orderBy('name')->get();

    assertType('int', $userOrAccount->increment('counter'));
    assertType('int', $userOrAccount->decrement('counter'));
}

/**
 * A wildcard-generic builder keeps its wildcard through ->where().
 *
 * @param Builder<*> $query
 */
function testWildcardBuilder(Builder $query): void
{
    assertType('Illuminate\Database\Eloquent\Builder<*>', $query->where('foo', 'bar'));
}

/**
 * Static model calls resolve through a class-string template parameter.
 *
 * @template T of Model
 *
 * @param  class-string<T>  $class
 * @return T
 */
function testFindOrFailOnClassString(string $class): mixed
{
    return $class::findOrFail(1);
}

/** self::query() resolves to a builder of the model even when it is final. */
final class FinalUser extends Model
{
    /** @return Builder<self> */
    public function testQuerySelf(): Builder
    {
        return self::query();
    }
}
