<?php

namespace ModelClassProperties;

use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 */
class PropertyBuilder extends Builder
{
}

/**
 * @template TKey of array-key
 * @template TModel
 * @extends Collection<TKey, TModel>
 */
class PropertyCollection extends Collection
{
}

/** @extends Factory<PropertyModel> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }
}

class PropertyModel extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected static string $builder = PropertyBuilder::class;
    protected static string $collectionClass = PropertyCollection::class;
    protected static string $factory = PropertyFactory::class;
}

class ChildPropertyModel extends PropertyModel
{
}

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 */
class AttributeBuilder extends Builder
{
}

/**
 * @template TKey of array-key
 * @template TModel
 * @extends Collection<TKey, TModel>
 */
class AttributeCollection extends Collection
{
}

/** @extends Factory<AttributeModel> */
class AttributeFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }
}

#[UseEloquentBuilder(AttributeBuilder::class)]
#[CollectedBy(AttributeCollection::class)]
#[UseFactory(AttributeFactory::class)]
class AttributeModel extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    protected static string $builder = PropertyBuilder::class;
    protected static string $collectionClass = PropertyCollection::class;
    protected static string $factory = PropertyFactory::class;
}

/** @extends Factory<MethodModel> */
class MethodFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }
}

class MethodModel extends Model
{
    /** @use HasFactory<MethodFactory> */
    use HasFactory;

    protected static string $builder = PropertyBuilder::class;
    protected static string $collectionClass = PropertyCollection::class;
    protected static string $factory = PropertyFactory::class;

    /** @return AttributeBuilder<MethodModel> */
    public function newEloquentBuilder($query): AttributeBuilder
    {
        return new AttributeBuilder($query);
    }

    /** @return AttributeCollection<int, MethodModel> */
    public function newCollection(array $models = []): AttributeCollection
    {
        return new AttributeCollection($models);
    }

    /** @return MethodFactory */
    protected static function newFactory(): Factory
    {
        return MethodFactory::new();
    }
}

/**
 * @param builder-of<PropertyModel> $propertyBuilder
 * @param collection-of<int, PropertyModel> $propertyCollection
 * @param factory-of<PropertyModel> $propertyFactory
 * @param builder-of<AttributeModel> $attributeBuilder
 * @param collection-of<int, AttributeModel> $attributeCollection
 * @param factory-of<AttributeModel> $attributeFactory
 * @param builder-of<MethodModel> $methodBuilder
 * @param collection-of<int, MethodModel> $methodCollection
 * @param factory-of<MethodModel> $methodFactory
 */
function test(
    Builder $propertyBuilder,
    Collection $propertyCollection,
    Factory $propertyFactory,
    Builder $attributeBuilder,
    Collection $attributeCollection,
    Factory $attributeFactory,
    Builder $methodBuilder,
    Collection $methodCollection,
    Factory $methodFactory,
): void {
    assertType('ModelClassProperties\PropertyBuilder<ModelClassProperties\PropertyModel>', $propertyBuilder);
    assertType('ModelClassProperties\PropertyCollection<int, ModelClassProperties\PropertyModel>', $propertyCollection);
    assertType('ModelClassProperties\PropertyFactory', $propertyFactory);

    assertType('ModelClassProperties\AttributeBuilder<ModelClassProperties\AttributeModel>', $attributeBuilder);
    assertType('ModelClassProperties\AttributeCollection<int, ModelClassProperties\AttributeModel>', $attributeCollection);
    assertType('ModelClassProperties\PropertyFactory', $attributeFactory);

    assertType('ModelClassProperties\AttributeBuilder<ModelClassProperties\MethodModel>', $methodBuilder);
    assertType('ModelClassProperties\AttributeCollection<int, ModelClassProperties\MethodModel>', $methodCollection);
    assertType('ModelClassProperties\MethodFactory', $methodFactory);

    assertType('ModelClassProperties\PropertyBuilder<ModelClassProperties\ChildPropertyModel>', ChildPropertyModel::query());
    assertType('ModelClassProperties\PropertyCollection<int, ModelClassProperties\ChildPropertyModel>', ChildPropertyModel::all());
    assertType('ModelClassProperties\PropertyFactory', ChildPropertyModel::factory());
}
