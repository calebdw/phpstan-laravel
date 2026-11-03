<?php

declare(strict_types=1);

namespace Tests\Unit;

use CalebDW\PhpstanLaravel\ReturnTypes\Methods\EloquentPluckExtension;
use CalebDW\PhpstanLaravel\Rules\CheckDispatchArgumentTypesCompatibleWithClassConstructorRule;
use CalebDW\PhpstanLaravel\Rules\ConfigAccessorRule;
use CalebDW\PhpstanLaravel\Rules\NoModelMakeRule;
use CalebDW\PhpstanLaravel\Types\AbortIfFunctionTypeSpecifyingExtension;
use PHPStan\Testing\PHPStanTestCase;

use function array_map;
use function get_class;

class AttributeServicesTest extends PHPStanTestCase
{
    public function testConditionalRulesRemainAvailableWithoutRunning(): void
    {
        $container = self::getContainer();
        $rules     = array_map(get_class(...), $container->getServicesByTag('phpstan.rules.rule'));

        self::assertNotContains(NoModelMakeRule::class, $rules);
        self::assertContains(ConfigAccessorRule::class, $rules);
        self::assertInstanceOf(NoModelMakeRule::class, $container->getByType(NoModelMakeRule::class));
    }

    public function testMultipleRegistrationsArePreserved(): void
    {
        $container = self::getContainer();

        self::assertCount(2, $container->findServiceNamesByType(CheckDispatchArgumentTypesCompatibleWithClassConstructorRule::class));
        self::assertCount(2, $container->findServiceNamesByType(EloquentPluckExtension::class));
        self::assertCount(4, $container->findServiceNamesByType(AbortIfFunctionTypeSpecifyingExtension::class));
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/attribute-services.neon'];
    }
}
