<?php

namespace ModelFactoryGenerics;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;
}

class SpecialProduct extends Product
{
}

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }
}

class NullableProduct extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected static function newFactory(): ?Factory
    {
        return null;
    }
}

class UnionProduct extends Model
{
    use HasFactory;

    protected static function newFactory(): FirstFactory|SecondFactory
    {
        return random_int(0, 1) === 0 ? new FirstFactory() : new SecondFactory();
    }
}

/** @extends Factory<UnionProduct> */
class FirstFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }
}

/** @extends Factory<UnionProduct> */
class SecondFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }
}

function test(bool $flag): void
{
    assertType('ModelFactoryGenerics\ProductFactory', Product::factory());
    assertType('ModelFactoryGenerics\Product', Product::factory()->create());
    assertType('Illuminate\Database\Eloquent\Collection<int, ModelFactoryGenerics\Product>', Product::factory(3)->create());
    assertType('ModelFactoryGenerics\ProductFactory', SpecialProduct::factory());
    assertType('ModelFactoryGenerics\ProductFactory', NullableProduct::factory());
    assertType('ModelFactoryGenerics\FirstFactory|ModelFactoryGenerics\SecondFactory', UnionProduct::factory());

    $model = $flag ? Product::class : UnionProduct::class;
    assertType('ModelFactoryGenerics\FirstFactory|ModelFactoryGenerics\ProductFactory|ModelFactoryGenerics\SecondFactory', $model::factory());
}

/** @param factory-of<Product|UnionProduct> $factory */
function factoryType(Factory $factory): void
{
    assertType('ModelFactoryGenerics\FirstFactory|ModelFactoryGenerics\ProductFactory|ModelFactoryGenerics\SecondFactory', $factory);
}
