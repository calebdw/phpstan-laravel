<?php

namespace Laravel13ModelClassProperties;

use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\HasCollection;
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

class ChildUsingTrait extends ParentModel
{
    use HasCollection;
}

#[CollectedBy(collectionClass: PropertyCollection::class)]
class NamedArgumentChild extends ParentModel
{
}

/** @param collection-of<int, ChildModel> $models */
function test(Collection $models): void
{
    assertType('Laravel13ModelClassProperties\ParentCollection', $models);
    assertType('Laravel13ModelClassProperties\ParentCollection', ChildModel::all());
    assertType('Laravel13ModelClassProperties\ParentCollection', ChildUsingTrait::all());
    assertType('Laravel13ModelClassProperties\ParentCollection', NamedArgumentChild::all());
}
