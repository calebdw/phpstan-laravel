<?php

declare(strict_types=1);

namespace Tests\Rules;

use CalebDW\PhpstanLaravel\Rules\ModelRelationDefaultsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<ModelRelationDefaultsRule> */
class ModelRelationDefaultsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(ModelRelationDefaultsRule::class);
    }

    public function testDefaults(): void
    {
        $this->analyse([__DIR__ . '/data/model-relation-defaults.php'], [
            ["Relation 'missing' is not found in ModelRelationDefaults\\InvalidDefaults model.", 19],
            ["Relation 'missing' is not found in App\\Account model.", 19],
            ["Relation 'missing' is not found in ModelRelationDefaults\\InvalidDefaults model.", 20],

            ["Relation 'accounts.transactions' is not found in ModelRelationDefaults\\InvalidDefaults model.", 20],
            ["Relation 'children' is not found in ModelRelationDefaults\\InvalidChild model.", 38],
            ["Relation 'children' is not found in ModelRelationDefaults\\InvalidChild model.", 38],
            ["Relation 'missing' is not found in App\\Account model.", 53],
            ["Relation 'missing' is not found in ModelRelationDefaults\\ConstantDefaults model.", 61],
        ]);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-rules.neon'];
    }
}
