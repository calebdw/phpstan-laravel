<?php

declare(strict_types=1);

namespace Tests\Rules\Queue;

use CalebDW\PhpstanLaravel\Rules\Queue\UniqueJobDeclaresUniqueForRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use function Orchestra\Testbench\laravel_version_compare;

/** @extends RuleTestCase<UniqueJobDeclaresUniqueForRule> */
class UniqueJobDeclaresUniqueForRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(UniqueJobDeclaresUniqueForRule::class);
    }

    public function testRule(): void
    {
        $files = [__DIR__ . '/data/unique-jobs.php'];
        $tip   = 'Declare a $uniqueFor property or a uniqueFor() method.';

        if (laravel_version_compare('13.0.0', '>=')) {
            $files[] = __DIR__ . '/data/l13-unique-for-attribute.php';
            $tip     = 'Declare a $uniqueFor property, a uniqueFor() method, or a UniqueFor attribute.';
        }

        $this->analyse($files, [
            [
                "Job Tests\\Rules\\Queue\\Data\\UniqueJobWithoutUniqueFor implements ShouldBeUnique but does not declare uniqueFor.\n    💡 " . $tip,
                47,
            ],
            [
                "Job Tests\\Rules\\Queue\\Data\\UniqueUntilProcessingJob implements ShouldBeUnique but does not declare uniqueFor.\n    💡 " . $tip,
                108,
            ],
        ]);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../phpstan-tests.neon'];
    }
}
