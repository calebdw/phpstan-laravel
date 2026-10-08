<?php

namespace CustomQueryBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
}

class FirstQuery extends QueryBuilder
{
    public function customFilter(): static
    {
        return $this;
    }

    /** @return 11 */
    public function count($columns = '*'): int
    {
        return 11;
    }

    public function exists(): bool
    {
        return true;
    }
}

class SecondQuery extends QueryBuilder
{
    public function customFilter(): static
    {
        return $this;
    }

    /** @return 22 */
    public function count($columns = '*'): int
    {
        return 22;
    }
}

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 * @method FirstQuery toBase()
 */
class CustomBuilder extends Builder
{
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
