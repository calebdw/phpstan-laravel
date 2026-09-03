<?php

declare(strict_types=1);

namespace Tests\Rules;

use CalebDW\PhpstanLaravel\Rules\NoModelMakeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use const DIRECTORY_SEPARATOR;

/** @extends RuleTestCase<NoModelMakeRule> */
class NoModelMakeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(NoModelMakeRule::class);
    }

    public function testNoFalsePositives(): void
    {
        $this->analyse([
            __DIR__ . '/data/CorrectModelInstantiation.php',
            __DIR__ . '/data/ModelMakeTrait.php',
        ], []);
    }

    public function testModelMake(): void
    {
        $this->analyse([__DIR__ . '/data/ModelMake.php'], [
            ["Called 'Model::make()' which performs unnecessary work, use 'new Model()'.", 15],
            ["Called 'Model::make()' which performs unnecessary work, use 'new Model()'.", 22],
        ]);
    }

    public function testModelMakeViaSelfStaticAndParent(): void
    {
        $this->analyse([__DIR__ . '/data/ModelMakeSelf.php'], [
            ["Called 'Model::make()' which performs unnecessary work, use 'new Model()'.", 13],
            ["Called 'Model::make()' which performs unnecessary work, use 'new Model()'.", 18],
            ["Called 'Model::make()' which performs unnecessary work, use 'new Model()'.", 23],
        ]);
    }

    public function testReportsModelMakeCallsInTraitRatherThanClass(): void
    {
        $actualErrors = $this->gatherAnalyserErrors([
            __DIR__ . '/data/ModelMake.php',
            __DIR__ . '/data/ModelMakeTrait.php',
        ]);

        $this->assertCount(3, $actualErrors);
        $this->assertSame(
            "Called 'Model::make()' which performs unnecessary work, use 'new Model()'.",
            $actualErrors[0]->getMessage(),
        );
        $this->assertSame(
            __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ModelMakeTrait.php (in context of class Tests\Rules\Data\ModelMake)',
            $actualErrors[0]->getFile(),
        );
        $this->assertSame(13, $actualErrors[0]->getLine());
    }

    public function testFix(): void
    {
        $this->fix(__DIR__ . '/data/ModelMake.php', __DIR__ . '/data/ModelMakeFixed.php');
        $this->fix(__DIR__ . '/data/ModelMakeSelf.php', __DIR__ . '/data/ModelMakeSelfFixed.php');
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../phpstan-tests.neon'];
    }
}
