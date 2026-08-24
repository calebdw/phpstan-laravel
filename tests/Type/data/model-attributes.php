<?php

declare(strict_types=1);

namespace ModelAttributes;

use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/** @extends Builder<Widget> */
class WidgetBuilder extends Builder
{
    /** @return $this */
    public function active(): static
    {
        return $this;
    }
}

/** @extends Collection<array-key, Widget> */
final class WidgetCollection extends Collection
{
}

/** @extends Factory<Widget> */
class WidgetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [];
    }
}

/**
 * Laravel's attributes are read, so none of these need a trait or a repeated
 * generic. #[UseFactory] is resolved the way HasFactory::newFactory() resolves
 * it at runtime, which a declared return type cannot express.
 */
#[UseEloquentBuilder(WidgetBuilder::class)]
#[CollectedBy(WidgetCollection::class)]
#[UseFactory(WidgetFactory::class)]
class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;
}

/** @extends Factory<Gadget> */
class GadgetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [];
    }
}

/** The attribute alone, with the trait's generic left undocumented. */
#[UseFactory(GadgetFactory::class)]
class Gadget extends Model
{
    use HasFactory;
}

function test(): void
{
    assertType('ModelAttributes\WidgetBuilder', Widget::query());
    assertType('ModelAttributes\WidgetBuilder', Widget::query()->active());
    assertType('ModelAttributes\WidgetCollection', Widget::all());
    assertType('ModelAttributes\WidgetFactory', Widget::factory());
    assertType('ModelAttributes\Widget', Widget::factory()->create());

    assertType('ModelAttributes\GadgetFactory', Gadget::factory());
    assertType('ModelAttributes\Gadget', Gadget::factory()->create());
}
