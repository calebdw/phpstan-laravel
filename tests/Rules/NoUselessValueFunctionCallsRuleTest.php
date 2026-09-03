<?php

declare(strict_types=1);

namespace Tests\Rules;

use CalebDW\PhpstanLaravel\Rules\NoUselessValueFunctionCallsRule;
use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<NoUselessValueFunctionCallsRule> */
class NoUselessValueFunctionCallsRuleTest extends RuleTestCase
{
    public function testNoFalsePositives(): void
    {
        $this->analyse(
            [
                __DIR__ . '/data/CorrectValueFunctionCall.php',
            ],
            [],
        );
    }

    public function testUselessWithCalls(): void
    {
        $this->analyse(
            [
                __DIR__ . '/data/UselessValueFunctionCall.php',
            ],
            [
                ["Calling the helper function 'value()' without a closure as the first argument simply returns the first argument without doing anything", 11],
                ["Calling the helper function 'value()' without a closure as the first argument simply returns the first argument without doing anything", 18],
            ],
        );
    }

    public function testFix(): void
    {
        $this->fix(__DIR__ . '/data/UselessValueFunctionCall.php', __DIR__ . '/data/UselessValueFunctionCallFixed.php');
    }

    protected function getRule(): Rule
    {
        return new NoUselessValueFunctionCallsRule(new CallHelper(new TypeHelper()));
    }
}
