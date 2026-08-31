<?php

declare(strict_types=1);

namespace Rules;

use CalebDW\PhpstanLaravel\Collectors\UsedEmailViewCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedRouteFacadeViewCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedViewFacadeMakeCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedViewFunctionCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedViewInAnotherViewCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedViewMakeCollector;
use CalebDW\PhpstanLaravel\Rules\UnusedViewsRule;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\ViewFileHelper;
use CalebDW\PhpstanLaravel\Support\ViewParser;
use PhpParser\Node;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<UnusedViewsRule> */
class UnusedViewsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        $viewParser     = new ViewParser($this->getContainer()->getService('currentPhpVersionSimpleDirectParser'));
        $viewFileHelper = new ViewFileHelper([
            __DIR__ . '/../application/resources/views/',
            __DIR__ . '/../../vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views',
        ], new FileHelper($this->getFileHelper()));

        return new UnusedViewsRule(new UsedViewInAnotherViewCollector($viewParser, $viewFileHelper), $viewFileHelper);
    }

    /** @return array<Collector<Node, mixed>> */
    protected function getCollectors(): array
    {
        return [
            new UsedViewFunctionCollector(),
            new UsedEmailViewCollector(),
            new UsedViewMakeCollector(),
            new UsedViewFacadeMakeCollector(),
            new UsedRouteFacadeViewCollector(),
        ];
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/FooController.php'], [
            [
                'This view is not used in the project.',
                00,
            ],
        ]);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../extension.neon',
        ];
    }
}
