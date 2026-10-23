<?php

namespace ModelCollectedBy;

use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\HasCollection;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/**
 * @template TKey of array-key
 * @template TModel of Model
 * @extends Collection<TKey, TModel>
 */
class Records extends Collection
{
}

#[CollectedBy('ModelCollectedBy\Records')]
class Record extends Model
{
}

#[CollectedBy('\ModelCollectedBy\Records')]
class LeadingSlash extends Model
{
}

#[CollectedBy(Records::class)]
class UsesTrait extends Model
{
    use HasCollection;
}

class Overridden extends Record
{
    /** @return Collection<int, static> */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }
}

function test(): void
{
    assertType('ModelCollectedBy\Records<int, ModelCollectedBy\Record>', Record::all());
    assertType('ModelCollectedBy\Records<int, ModelCollectedBy\LeadingSlash>', LeadingSlash::all());
    assertType('ModelCollectedBy\Records<int, ModelCollectedBy\UsesTrait>', UsesTrait::all());
    assertType('Illuminate\Database\Eloquent\Collection<int, ModelCollectedBy\Overridden>', Overridden::all());
}
