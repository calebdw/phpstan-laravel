<?php

namespace InheritedBuilderAttributes;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 */
class ParentBuilder extends Builder
{
}

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 */
class ChildBuilder extends Builder
{
}

#[UseEloquentBuilder(ParentBuilder::class)]
class ParentModel extends Model
{
}

class ChildModel extends ParentModel
{
}

#[UseEloquentBuilder(ChildBuilder::class)]
trait HasBuilder
{
}

class TraitModel extends ParentModel
{
    use HasBuilder;
}

#[UseEloquentBuilder(ParentBuilder::class)]
class ClassWins extends Model
{
    use HasBuilder;
}

trait NestedBuilder
{
    use HasBuilder;
}

class NestedModel extends Model
{
    use NestedBuilder;
}

class TraitParent extends Model
{
    use HasBuilder;
}

class TraitChild extends TraitParent
{
}

#[UseEloquentBuilder(Model::class)]
class InvalidChild extends ParentModel
{
}

function builders(): void
{
    assertType('InheritedBuilderAttributes\ParentBuilder<InheritedBuilderAttributes\ChildModel>', ChildModel::query());
    assertType('InheritedBuilderAttributes\ChildBuilder<InheritedBuilderAttributes\TraitModel>', TraitModel::query());
    assertType('InheritedBuilderAttributes\ParentBuilder<InheritedBuilderAttributes\ClassWins>', ClassWins::query());
    assertType('Illuminate\Database\Eloquent\Builder<InheritedBuilderAttributes\NestedModel>', NestedModel::query());
    assertType('InheritedBuilderAttributes\ChildBuilder<InheritedBuilderAttributes\TraitChild>', TraitChild::query());
    assertType('Illuminate\Database\Eloquent\Builder<InheritedBuilderAttributes\InvalidChild>', InvalidChild::query());
}
