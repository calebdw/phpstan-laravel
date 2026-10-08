<?php

namespace CustomQueryBuilder;

use App\FirstCustomQueryBuilder as FirstQuery;
use App\SecondCustomQueryBuilder as SecondQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

function queries(): void
{
    assertType('CustomQueryBuilder\CustomBuilder<CustomQueryBuilder\Record>', Record::query()->customFilter());
    assertType('CustomQueryBuilder\Record|null', Record::customFilter()->first());
    assertType('bool', Record::query()->exists());
    assertType('11', Record::query()->count());
    assertType('CustomQueryBuilder\CustomBuilder<CustomQueryBuilder\Record>', Record::query()->where('id', 1));

    assertType('CustomQueryBuilder\UnionBuilder<CustomQueryBuilder\UnionRecord>', UnionRecord::query()->customFilter());
    assertType('11|22', UnionRecord::query()->count());
    assertType('bool', UnionRecord::query()->exists());
    assertType('int', Record::query()->toBase()->customScalar(1));
    assertType('int|string', UnionRecord::query()->toBase()->customScalar(1));
    assertType('CustomQueryBuilder\CustomBuilder<CustomQueryBuilder\Record>', Record::query()->customScalar(1));
    assertType('CustomQueryBuilder\UnionBuilder<CustomQueryBuilder\UnionRecord>', UnionRecord::query()->customScalar(1));
    assertType('CustomQueryBuilder\Record|null', Record::customScalar(1)->first());
}

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 */
class CustomBuilder extends Builder
{
    public function toBase(): FirstQuery
    {
        return new FirstQuery($this->getQuery()->getConnection());
    }
}

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 * @method FirstQuery|SecondQuery toBase()
 */
class UnionBuilder extends Builder
{
}

class Record extends Model
{
    /** @return CustomBuilder<static> */
    public function newEloquentBuilder($query): CustomBuilder
    {
        return new CustomBuilder($query);
    }
}

class UnionRecord extends Model
{
    /** @return UnionBuilder<static> */
    public function newEloquentBuilder($query): UnionBuilder
    {
        return new UnionBuilder($query);
    }
}
