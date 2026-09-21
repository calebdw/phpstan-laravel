<?php

namespace Laravel12ModelClassProperties;

use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/** @extends Collection<int, ParentModel> */
class ParentCollection extends Collection
{
}

/** @extends Collection<int, ChildModel> */
class PropertyCollection extends Collection
{
}

#[CollectedBy(ParentCollection::class)]
class ParentModel extends Model
{
    protected static string $collectionClass = PropertyCollection::class;
}

class ChildModel extends ParentModel
{
}

/** @param collection-of<int, ChildModel> $models */
function test(Collection $models): void
{
    assertType('Laravel12ModelClassProperties\PropertyCollection', $models);
    assertType('Laravel12ModelClassProperties\PropertyCollection', ChildModel::all());
}
