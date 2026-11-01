<?php

namespace LegacyBuilderAttributes;

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

#[UseEloquentBuilder(CustomBuilder::class)]
class ParentModel extends Model
{
}

class ChildModel extends ParentModel
{
}

#[UseEloquentBuilder(CustomBuilder::class)]
trait HasBuilder
{
}

class TraitModel extends Model
{
    use HasBuilder;
}

function builders(): void
{
    assertType('Illuminate\Database\Eloquent\Builder<LegacyBuilderAttributes\ChildModel>', ChildModel::query());
    assertType('Illuminate\Database\Eloquent\Builder<LegacyBuilderAttributes\TraitModel>', TraitModel::query());
}
