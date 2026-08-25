<?php

declare(strict_types=1);

namespace EloquentBuilder;

use App\Post;
use App\PostBuilder;
use App\Team;
use App\User;
use App\Address;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

use function PHPStan\Testing\assertType;

interface OnlyUsers
{ }

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @param Builder<User> $userBuilder
 * @param Builder<User>|\App\ChildTeamBuilder $userOrTeamBuilder
 * @param Builder<TModel> $templateBuilder
 */
function test(
    User $user,
    Post $post,
    Builder $userBuilder,
    OnlyUsers&User $userAndAuth,
    Builder $userOrTeamBuilder,
    Builder $templateBuilder,
): void {
    User::query()->has('accounts', callback: function ($query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    User::query()->has('accounts', '=', 1, 'and', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    User::query()->has('accounts.posts', '=', 1, 'and', function (Builder $query) {
        assertType('App\PostBuilder<App\Post>', $query);
    });

    Post::query()->has('users', '=', 1, 'and', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
    });

    User::query()->doesntHave('accounts', 'and', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    User::query()->whereHas('accounts', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    User::query()->withWhereHas('accounts.posts', function (Builder|Relation $query) {
        assertType('App\PostBuilder<App\Post>|Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\Account, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>', $query);
    });

    Post::query()->withWhereHas('users', function (Builder|Relation $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>|Illuminate\Database\Eloquent\Relations\BelongsToMany<App\User, App\Post, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>', $query);
    });

    User::query()->orWhereHas('accounts', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    User::query()->whereDoesntHave('accounts', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    User::query()->orWhereDoesntHave('accounts', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    Post::query()->whereRelation('users', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
    });

    User::query()->orWhereRelation('accounts', function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    $relation = random_int(0, 1) ? 'accounts' : 'address';
    User::query()->whereHas($relation, function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account|App\Address>', $query);
    });
    User::query()->withWhereHas($relation, function (Builder|Relation $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account|App\Address>|Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>|Illuminate\Database\Eloquent\Relations\MorphMany<App\Address, App\User>', $query);
    });

    $relation = random_int(0, 1) ? 'accounts.posts' : 'address';
    User::query()->whereHas($relation, function (Builder $query) {
        assertType('App\PostBuilder<App\Post>|Illuminate\Database\Eloquent\Builder<App\Address>', $query);
    });
    User::query()->withWhereHas($relation, function (Builder|Relation $query) {
        assertType('App\PostBuilder<App\Post>|Illuminate\Database\Eloquent\Builder<App\Address>|Illuminate\Database\Eloquent\Relations\BelongsToMany<App\Post, App\Account, Illuminate\Database\Eloquent\Relations\Pivot, \'pivot\'>|Illuminate\Database\Eloquent\Relations\MorphMany<App\Address, App\User>', $query);
    });

    $relation = random_int(0, 1) ? $user->accounts() : $user->address();
    User::query()->whereHas($relation, function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account|App\Address>', $query);
    });
    User::query()->withWhereHas($relation, function (Builder|Relation $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account|App\Address>|Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>|Illuminate\Database\Eloquent\Relations\MorphMany<App\Address, App\User>', $query);
    });


    $user->has($user->accounts(), callback: function ($query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>', $query);
    });

    $user->withWhereHas($user->accounts(), function (Builder|Relation $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Account>|Illuminate\Database\Eloquent\Relations\HasMany<App\Account, App\User>', $query);
    });

    $userOrTeamBuilder->has('address', function ($query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Address>', $query);
    });

    $userOrTeamBuilder->has('members', function ($query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
    });

    $userOrTeamBuilder->has('transactions', function ($query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\Transaction>', $query);
    });

    Address::query()->hasMorph('addressable', [User::class, Team::class], callable: function ($query, $morph) {
        assertType('App\ChildTeamBuilder|Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    Address::query()->hasMorph('addressable', User::class, '=', 1, 'and', function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    Address::query()->hasMorph('addressable', '*', callback: function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<Illuminate\Database\Eloquent\Model>', $query);
        assertType('string', $morph);
    });

    Address::query()->doesntHaveMorph('addressable', [User::class], function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    Address::query()->whereHasMorph('addressable', [User::class], function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    Address::query()->orWhereHasMorph('addressable', [User::class], function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    Address::query()->whereDoesntHaveMorph('addressable', [User::class], function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    Address::query()->orWhereDoesntHaveMorph('addressable', [User::class], function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    Address::query()->whereMorphRelation('addressable', [User::class], function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    Address::query()->orWhereMorphRelation('addressable', [User::class], function (Builder $query, $morph) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
        assertType('string', $morph);
    });

    User::query()->firstWhere(function (Builder $query) {
        assertType('Illuminate\Database\Eloquent\Builder<App\User>', $query);
    });

    Post::query()->firstWhere(function (PostBuilder $query) {
        assertType('App\PostBuilder<App\Post>', $query);
    });

    Post::query()->where(static function (PostBuilder $query) {
        assertType('App\PostBuilder<App\Post>', $query
            ->orWhere('bar', 'LIKE', '%foo%')
            ->orWhereRelation('users', 'name', 'LIKE', '%foo%'));
    });

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::where([
        ['active', true],
        ['id', '>=', 5],
        ['id', '<=', 10],
    ])->get());

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::where('id', 1)->get());
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', (new User())->where('id', 1)->get());
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::where('id', 1)
        ->whereNotNull('name')
        ->where('email', 'bar')
        ->whereFoo(['bar'])
        ->get());
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', (new User())->whereNotNull('name')
        ->where('email', 'bar')
        ->whereFoo(['bar'])
        ->get());
    assertType('Illuminate\Support\Collection<string, string>', User::whereIn('id', [1, 2, 3])->get()->mapWithKeys(function (User $user): array {
        return [$user->name => $user->email];
    }));

    assertType('mixed', (new User())->where('email', 1)->max('email'));
    assertType('bool', (new User())->where('email', 1)->exists());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::with('accounts')->whereNull('name'));
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::with('accounts')
        ->where('email', 'bar')
        ->orWhere('name', 'baz'));

    assertType('App\User|null', User::with(['accounts'])->find(1));
    assertType('App\User', User::with(['accounts'])->findOrFail(1));
    assertType('App\User', User::with(['accounts'])->findOrNew(1));
    assertType('Illuminate\Database\Eloquent\Model|null', (new CustomBuilder(User::query()->getQuery()))->with('email')->find(1));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::with(['accounts'])->find([1, 2, 3]));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::with(['accounts'])->findOrNew([1, 2, 3]));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::hydrate([]));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', User::fromQuery('SELECT * FROM users'));

    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userBuilder->whereNotNull('test'));

    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $user->newQuery());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $user->newModelQuery());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $user->newQueryWithoutRelationships());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $user->newQueryWithoutScopes());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $user->newQueryWithoutScope('foo'));
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $user->newQueryForRestoration([1]));

    assertType('App\PostBuilder<App\Post>', $post->newQuery());
    assertType('App\PostBuilder<App\Post>', $post->newModelQuery());
    assertType('App\PostBuilder<App\Post>', $post->newQueryWithoutRelationships());
    assertType('App\PostBuilder<App\Post>', $post->newQueryWithoutScopes());
    assertType('App\PostBuilder<App\Post>', $post->newQueryWithoutScope('foo'));
    assertType('App\PostBuilder<App\Post>', $post->newQueryForRestoration([1]));

    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userAndAuth->newQuery());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userAndAuth->newModelQuery());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userAndAuth->newQueryWithoutRelationships());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userAndAuth->newQueryWithoutScopes());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userAndAuth->newQueryWithoutScope('foo'));
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userAndAuth->newQueryForRestoration([1]));
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userAndAuth::query());

    assertType('Illuminate\Support\LazyCollection<int, App\User>', User::query()->lazy());
    assertType('Illuminate\Support\LazyCollection<int, App\User>', User::query()->lazyById());
    assertType('Illuminate\Support\LazyCollection<int, App\User>', User::query()->lazyByIdDesc());
    assertType('Illuminate\Support\LazyCollection<int, App\User>', User::query()->cursor());
    assertType('Illuminate\Support\LazyCollection<int, App\Post>', $post->newQuery()->lazy());
    assertType('Illuminate\Support\LazyCollection<int, App\Post>', $post->newQuery()->lazyById());
    assertType('Illuminate\Support\LazyCollection<int, App\Post>', $post->newQuery()->lazyByIdDesc());

    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::query()->groupBy('foo', 'bar'));
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::query()->whereEmail('bar'));
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::query()->whereIdAndEmail(1, 'foo@example.com'));
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::query()->whereEmail(1));
    assertType(
        'Illuminate\Database\Query\Builder',
        User::query()
        ->whereNull('name')
        ->orderBy('email')
        ->toBase()
    );
    assertType(
        'Illuminate\Database\Query\Builder',
        User::query()
        ->whereNotBetween('a', [1, 5])
        ->orWhereNotBetween('a', [1, 5])
        ->toBase()
    );
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::query()->withTrashed());
    assertType(
        'Illuminate\Database\Query\Builder',
        User::query()
        ->whereNull('name')
        ->orderBy(\Illuminate\Support\Facades\DB::raw('name'))
        ->toBase()
    );
    assertType(
        'Illuminate\Database\Query\Builder',
        User::query()
        ->whereNull('name')
        ->orderBy(User::whereNotNull('name'))
        ->toBase()
    );
    assertType(
        'Illuminate\Database\Query\Builder',
        User::query()
        ->whereNull('name')
        ->latest(\Illuminate\Support\Facades\DB::raw('created_at'))
        ->toBase()
    );
    assertType(
        'Illuminate\Database\Query\Builder',
        User::query()
        ->whereNull('name')
        ->oldest(\Illuminate\Support\Facades\DB::raw('created_at'))
        ->toBase()
    );
    assertType(
        'Illuminate\Support\Collection<int, mixed>',
        User::query()
        ->whereNull('name')
        ->pluck(\Illuminate\Support\Facades\DB::raw('created_at'))
        ->toBase()
    );
    assertType('int', User::query()->increment(\Illuminate\Support\Facades\DB::raw('counter')));
    assertType('int', User::query()->decrement(\Illuminate\Support\Facades\DB::raw('counter')));
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', $userBuilder->macro('customMacro', function () {
    }));
    assertType('string', $userBuilder->globalCustomMacro('foo'));
    assertType(
        'App\User',
        User::with('accounts')
        ->where('email', 'bar')
        ->orWhere('name', 'baz')
        ->firstOrFail()
    );
    assertType(
        'App\User|null',
        User::with('accounts')
        ->where('email', 'bar')
        ->orWhere('name', 'baz')
        ->first()
    );
    assertType('App\User|null', User::query()->firstWhere(['email' => 'foo@bar.com']));
    assertType(
        'App\User|null',
        User::with('accounts')
        ->orWhere(\Illuminate\Support\Facades\DB::raw('name'), 'like', '%john%')
        ->first()
    );
    assertType(
        'App\User|null',
        User::with('accounts')
        ->where(\Illuminate\Support\Facades\DB::raw('name'), 'like', '%john%')
        ->first()
    );
    assertType(
        'App\User|null',
        User::with('accounts')
        ->firstWhere(\Illuminate\Support\Facades\DB::raw('name'), 'like', '%john%')
    );
    assertType(
        'mixed',
        User::with('accounts')
        ->value(\Illuminate\Support\Facades\DB::raw('name'))
    );

    assertType('string|null', User::query()->value('name'));
    assertType('int|null', User::query()->value('id'));
    assertType('bool|null', User::query()->value('blocked'));
    assertType('string|null', User::query()->value('propertyDefinedOnlyInAnnotation'));
    assertType('string', User::query()->soleValue('name'));
    assertType('int', User::query()->soleValue('id'));
    assertType('string', User::query()->valueOrFail('name'));
    assertType('int', User::query()->valueOrFail('id'));
    assertType('Carbon\Carbon|null', User::query()->valueOrFail('email_verified_at'));

    assertType('int', User::query()->restore());
    assertType('Illuminate\Database\Eloquent\Builder<App\User>', User::query()->joinSub(
        Post::query()->whereIn('id', [1, 2, 3]),
        'users',
        'users.id',
        'posts.id'
    ));

    assertType('Illuminate\Pagination\LengthAwarePaginator<int, App\User>', User::query()->paginate());
    assertType('array<int, App\User>', User::query()->paginate()->items());

    User::chunk(1000, fn ($collection) => assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection));
    User::chunkById(1000, fn ($collection) => assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection));
    assertType('Illuminate\Support\Collection<int, string>', User::chunkMap(function ($model) {
        assertType('App\User', $model);

        return $model->name;
    }, 1000));
    $userBuilder->chunk(1000, fn ($collection) => assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection));
    $userBuilder->chunkById(1000, fn ($collection) => assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $collection));
    assertType('Illuminate\Support\Collection<int, string>', $userBuilder->chunkMap(function ($model) {
        assertType('App\User', $model);

        return $model->name;
    }, 1000));

    assertType('App\Team|App\User', $userOrTeamBuilder->findOrFail(4));
    assertType('App\ChildTeamBuilder|Illuminate\Database\Eloquent\Builder<App\User>', $userOrTeamBuilder->where('id', 5));

    assertType('Illuminate\Database\Eloquent\Builder<TModel of Illuminate\Database\Eloquent\Model (function EloquentBuilder\test(), argument)>', $templateBuilder->select());

    assertType('EloquentBuilder\AttributeBuilder', AttributeModel::query());
    assertType('EloquentBuilder\AttributeOverrideBuilder', AttributeOverrideModel::query());
}

class Foo extends Model
{
    /** @use FooTrait<Foo> */
    use FooTrait;
}

/** @template TModel of Model */
trait FooTrait
{
    /** @return Builder<TModel> */
    public function doFoo(): Builder
    {
        return $this->newQuery();
    }
}

/** @property string $email */
class TestModel extends Model
{
    public function test(): void
    {
        assertType('Illuminate\Database\Eloquent\Collection<int, static(EloquentBuilder\TestModel)>', $this->where('email', 1)->get());
        assertType('Illuminate\Database\Eloquent\Builder<static(EloquentBuilder\TestModel)>', static::query()->where('email', 'bar'));
        assertType('Illuminate\Database\Eloquent\Builder<static(EloquentBuilder\TestModel)>', $this->where('email', 'bar'));
    }
}

/** @extends Builder<Model> */
class CustomBuilder extends Builder
{
}

/** @template TModel of User|Team */
abstract class UnionClass
{
    /** @return TModel */
    public function test(int $id): Model
    {
        assertType('TModel of App\Team|App\User (class EloquentBuilder\UnionClass, argument)', $this->getQuery()->findOrFail($id));

        return $this->getQuery()->findOrFail($id);
    }

    /** @return Builder<TModel> */
    abstract public function getQuery(): Builder;
}

/** @extends UnionClass<Team> */
class TeamClass extends UnionClass
{
    public function foo(): void
    {
        assertType('App\Team', $this->test(5));
    }

    /** @inheritDoc */
    public function getQuery(): Builder
    {
        return Team::query();
    }
}

#[UseEloquentBuilder(AttributeBuilder::class)]
class AttributeModel extends Model
{
}

/** An explicit newEloquentBuilder() wins over the attribute. */
#[UseEloquentBuilder(AttributeBuilder::class)]
class AttributeOverrideModel extends Model
{
    public function newEloquentBuilder($query): AttributeOverrideBuilder
    {
        return new AttributeOverrideBuilder($query);
    }
}

/** @extends Builder<AttributeModel> */
class AttributeBuilder extends Builder
{
}

/** @extends Builder<AttributeOverrideModel> */
class AttributeOverrideBuilder extends Builder
{
}

/**
 * A class-level template parameter survives calls through the builder.
 *
 * @template TModel of Model
 */
class TemplatedRepository
{
    /** @var Builder<TModel> */
    private Builder $query;

    public function testSelect(): void
    {
        assertType('Illuminate\Database\Eloquent\Builder<TModel of Illuminate\Database\Eloquent\Model (class EloquentBuilder\TemplatedRepository, argument)>', $this->query->select());
    }

    /** @return Collection<int, TModel> */
    public function testGet(): Collection
    {
        $models = $this->query->get();
        assertType('Illuminate\Database\Eloquent\Collection<int, TModel of Illuminate\Database\Eloquent\Model (class EloquentBuilder\TemplatedRepository, argument)>', $models);

        return $models;
    }
}
