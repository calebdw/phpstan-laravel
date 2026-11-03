<?php

namespace BelongsToManyPivotAccessor;

use App\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

use function PHPStan\Testing\assertType;

class Subscription extends Pivot {}

class Podcast extends Model {}

class Member extends Model
{
    /** @return BelongsToMany<Podcast, $this, Subscription, 'subscription'> */
    public function podcasts(): BelongsToMany
    {
        return $this->belongsToMany(Podcast::class)->as('subscription')->using(Subscription::class);
    }

    /** @return MorphToMany<Podcast, $this, MorphPivot, 'tagging'> */
    public function taggedPodcasts(): MorphToMany
    {
        return $this->morphToMany(Podcast::class, 'taggable')->as('tagging');
    }

    /** @return BelongsToMany<Role, $this, Subscription, 'assignment'> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->as('assignment')->using(Subscription::class);
    }
}

function test(Member $member, string $accessor, bool $condition): void
{
    assertType('BelongsToManyPivotAccessor\Subscription', $member->podcasts()->firstOrFail()->subscription);
    assertType('(BelongsToManyPivotAccessor\Podcast&object{subscription: BelongsToManyPivotAccessor\Subscription})|null', $member->podcasts()->first());
    assertType('BelongsToManyPivotAccessor\Subscription', $member->podcasts()->findOrFail(1)->subscription);
    assertType('BelongsToManyPivotAccessor\Subscription', $member->podcasts()->create()->subscription);
    assertType('BelongsToManyPivotAccessor\Subscription', $member->podcasts()->incrementOrCreate(['id' => 1])->subscription);
    assertType('BelongsToManyPivotAccessor\Subscription', $member->belongsToMany(Podcast::class)->using(Subscription::class)->as('subscription')->firstOrFail()->subscription);
    assertType('Illuminate\Database\Eloquent\Collection<int, BelongsToManyPivotAccessor\Podcast&object{subscription: BelongsToManyPivotAccessor\Subscription}>', $member->podcasts()->get());
    assertType('Illuminate\Database\Eloquent\Collection<int, BelongsToManyPivotAccessor\Podcast&object{subscription: BelongsToManyPivotAccessor\Subscription}>', $member->podcasts()->getResults());
    assertType('BelongsToManyPivotAccessor\Subscription', $member->podcasts->firstOrFail()->subscription);
    assertType('App\RoleCollection<int, App\Role&object{assignment: BelongsToManyPivotAccessor\Subscription}>', $member->roles()->get());
    assertType('BelongsToManyPivotAccessor\Subscription', $member->roles->firstOrFail()->assignment);
    assertType('Illuminate\Database\Eloquent\Relations\MorphPivot', $member->taggedPodcasts()->firstOrFail()->tagging);
    assertType('Illuminate\Database\Eloquent\Relations\MorphPivot', $member->taggedPodcasts->firstOrFail()->tagging);
    assertType('Illuminate\Pagination\LengthAwarePaginator<int, BelongsToManyPivotAccessor\Podcast&object{subscription: BelongsToManyPivotAccessor\Subscription}>', $member->podcasts()->paginate());
    assertType('Illuminate\Support\LazyCollection<int, BelongsToManyPivotAccessor\Podcast&object{subscription: BelongsToManyPivotAccessor\Subscription}>', $member->podcasts()->cursor());

    $member->podcasts()->each(fn ($p) => assertType('BelongsToManyPivotAccessor\Subscription', $p->subscription));
    $member->podcasts()->chunk(10, fn ($c) => assertType('BelongsToManyPivotAccessor\Subscription', $c->firstOrFail()->subscription));

    assertType('BelongsToManyPivotAccessor\Podcast|null', $member->belongsToMany(Podcast::class)->as($accessor)->first());
    assertType('BelongsToManyPivotAccessor\Podcast|null', $member->belongsToMany(Podcast::class)->as($condition ? 'one' : 'two')->first());
}

/** @param BelongsToMany<Podcast|Role, Member, Subscription, 'subscription'> $relation */
function unionModels(BelongsToMany $relation): void
{
    assertType('BelongsToManyPivotAccessor\Subscription', $relation->get()->firstOrFail()->subscription);
    assertType('BelongsToManyPivotAccessor\Subscription', $relation->getResults()->firstOrFail()->subscription);
}

/**
 * @template T of Model
 * @param BelongsToMany<T, Member, Subscription, 'subscription'> $relation
 */
function templateModel(BelongsToMany $relation): void
{
    assertType('BelongsToManyPivotAccessor\Subscription', $relation->get()->firstOrFail()->subscription);
    assertType('BelongsToManyPivotAccessor\Subscription', $relation->getResults()->firstOrFail()->subscription);
}
