<?php

namespace BuilderAttributes;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 */
class CustomBuilder extends Builder
{
}

#[UseEloquentBuilder(builderClass: CustomBuilder::class)]
class NamedModel extends Model
{
}

#[UseEloquentBuilder('BuilderAttributes\MissingBuilder')]
class MissingModel extends Model
{
}

#[UseEloquentBuilder(Model::class)]
class InvalidModel extends Model
{
}

#[UseEloquentBuilder('\BuilderAttributes\CustomBuilder')]
class StringModel extends Model
{
}

#[UseEloquentBuilder(CustomBuilder::class)]
class OverrideModel extends Model
{
    /** @return Builder<static> */
    public function newEloquentBuilder($query): Builder
    {
        return new Builder($query);
    }
}

function builders(): void
{
    assertType('BuilderAttributes\CustomBuilder<BuilderAttributes\NamedModel>', NamedModel::query());
    assertType('BuilderAttributes\CustomBuilder<BuilderAttributes\StringModel>', StringModel::query());
    assertType('Illuminate\Database\Eloquent\Builder<BuilderAttributes\MissingModel>', MissingModel::query());
    assertType('Illuminate\Database\Eloquent\Builder<BuilderAttributes\InvalidModel>', InvalidModel::query());
    assertType('Illuminate\Database\Eloquent\Builder<BuilderAttributes\OverrideModel>', OverrideModel::query());
}
