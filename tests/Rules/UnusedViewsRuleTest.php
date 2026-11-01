<?php

declare(strict_types=1);

namespace Rules;

use CalebDW\PhpstanLaravel\Collectors\UsedViewCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedViewInAnotherViewCollector;
use CalebDW\PhpstanLaravel\Rules\UnusedViewsRule;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\ViewFileHelper;
use CalebDW\PhpstanLaravel\Support\ViewParser;
use PhpParser\Node;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<UnusedViewsRule> */
class UnusedViewsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        $containerHelper            = self::getContainer()->getByType(ContainerHelper::class);
        $viewFileHelper             = new ViewFileHelper([
            __DIR__ . '/../application/resources/views/',
            __DIR__ . '/../../vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views',
        ], self::getContainer()->getByType(FileHelper::class), $containerHelper);
        $usedInAnotherViewCollector = new UsedViewInAnotherViewCollector(
            self::getContainer()->getByType(ViewParser::class),
            $viewFileHelper,
        );

        return new UnusedViewsRule($viewFileHelper, $usedInAnotherViewCollector, $containerHelper);
    }

    /** @return array<Collector<Node, mixed>> */
    protected function getCollectors(): array
    {
        return [
            self::getContainer()->getByType(UsedViewCollector::class),
        ];
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/FooController.php'], [
            [
                'This view is not used in the project.',
                00,
            ],
            [
                'This view is not used in the project.',
                00,
            ],
        ]);
    }

    public function testTracksViewDirectories(): void
    {
        $tracked = [];
        $scope   = $this->createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        $scope->method('trackDirectoryDependency')->willReturnCallback(static function (string $directory, string $pattern) use (&$tracked): void {
            $tracked[] = [$directory, $pattern];
        });

        $this->getRule()->processNode(new CollectedDataNode([], false), $scope);

        $this->assertSame([
            [__DIR__ . '/../application/resources/views/', '*'],
            [__DIR__ . '/../../vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views', '*'],
        ], $tracked);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../extension.neon',
        ];
    }
}
