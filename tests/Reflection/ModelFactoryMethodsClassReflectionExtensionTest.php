<?php

declare(strict_types=1);

namespace Reflection;

use CalebDW\PhpstanLaravel\Methods\ModelFactoryMethodsClassReflectionExtension;
use CalebDW\PhpstanLaravel\Reflection\ModelFactoryMethodReflection;
use Database\Factories\UserFactory;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;

class ModelFactoryMethodsClassReflectionExtensionTest extends PHPStanTestCase
{
    private ReflectionProvider $reflectionProvider;

    private ModelFactoryMethodsClassReflectionExtension $reflectionExtension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reflectionProvider  = $this->createReflectionProvider();
        $this->reflectionExtension = new ModelFactoryMethodsClassReflectionExtension($this->reflectionProvider);
    }

    #[Test]
    public function it_creates_for_method_overloads(): void
    {
        $method = $this->reflectionExtension->getMethod($this->reflectionProvider->getClass(UserFactory::class), 'forUser');

        $this->assertInstanceOf(ModelFactoryMethodReflection::class, $method);
        $this->assertSame('forUser', $method->getName());
        $this->assertCount(2, $method->getVariants());
        $this->assertSame([], $method->getVariants()[0]->getParameters());
        $this->assertSame('state', $method->getVariants()[1]->getParameters()[0]->getName());
    }

    #[Test]
    public function it_creates_has_method_overloads(): void
    {
        $method = $this->reflectionExtension->getMethod($this->reflectionProvider->getClass(UserFactory::class), 'hasPosts');

        $this->assertInstanceOf(ModelFactoryMethodReflection::class, $method);
        $this->assertSame('hasPosts', $method->getName());
        $this->assertCount(4, $method->getVariants());
        $this->assertSame([], $method->getVariants()[0]->getParameters());
        $this->assertSame('count', $method->getVariants()[1]->getParameters()[0]->getName());
        $this->assertSame('state', $method->getVariants()[2]->getParameters()[0]->getName());
        $this->assertSame('count', $method->getVariants()[3]->getParameters()[0]->getName());
        $this->assertSame('state', $method->getVariants()[3]->getParameters()[1]->getName());
    }
}
