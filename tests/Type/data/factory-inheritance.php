<?php

namespace FactoryInheritance;

use App\FactoryChild;
use App\FactoryParent;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

use function PHPStan\Testing\assertType;

class PinnedParent extends FactoryChild
{
    protected static string $factory = UserFactory::class;
}

class PinnedChild extends PinnedParent
{
}

class OverrideParent extends FactoryChild
{
    public static function factory($count = null, $state = []): UserFactory
    {
        return UserFactory::new();
    }
}

class OverrideChild extends OverrideParent
{
}

#[UseFactory(UserFactory::class)]
class AttributeChild extends FactoryChild
{
}

function factories(bool $flag): void
{
    assertType('Database\Factories\UserFactory', FactoryParent::factory());
    assertType('Database\Factories\FactoryChildFactory', FactoryChild::factory());
    assertType('App\FactoryChild', FactoryChild::factory()->create());
    assertType('Database\Factories\UserFactory', PinnedChild::factory());
    assertType('Database\Factories\UserFactory', OverrideChild::factory());
    assertType('Database\Factories\UserFactory', AttributeChild::factory());

    $class = $flag ? \App\Post::class : FactoryChild::class;
    assertType('Database\Factories\FactoryChildFactory|Database\Factories\Post\PostFactory', $class::factory());
}

/** @param factory-of<FactoryChild|\App\Post> $factory */
function factoryType(Factory $factory): void
{
    assertType('Database\Factories\FactoryChildFactory|Database\Factories\Post\PostFactory', $factory);
}
