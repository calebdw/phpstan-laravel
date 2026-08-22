<?php

namespace CustomModelCollectionUnique;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use function PHPStan\Testing\assertType;

function test(): void
{
    assertType('CustomModelCollectionUnique\ModelCollection<int, CustomModelCollectionUnique\User>', User::all()->unique());
}

class User extends Model
{
    /** @return ModelCollection<int, User> */
    public function newCollection(array $models = []): ModelCollection
    {
        return new ModelCollection($models);
    }
}

/**
 * @template TKey of array-key
 * @template TModel of Model
 *
 * @extends Collection<TKey, TModel>
 */
class ModelCollection extends Collection
{
}
