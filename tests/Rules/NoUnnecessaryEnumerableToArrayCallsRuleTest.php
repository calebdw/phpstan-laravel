<?php

declare(strict_types=1);

namespace Tests\Rules;

use CalebDW\PhpstanLaravel\Rules\NoUnnecessaryEnumerableToArrayCallsRule;
use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<NoUnnecessaryEnumerableToArrayCallsRule> */
class NoUnnecessaryEnumerableToArrayCallsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoUnnecessaryEnumerableToArrayCallsRule(new CallHelper(new TypeHelper()), new TypeHelper());
    }

    public function test_rule(): void
    {
        $message = "Called [toArray()] on an Enumerable which does not contain any Arrayables.\n    💡 Use [all()] to get the items as an array.";

        $this->analyse([__DIR__ . '/data/unnecessary-enumerable-toArray-calls.php'], [
            [$message, 27],
        ]);
    }

    public function testFix(): void
    {
        $this->fix(__DIR__ . '/data/unnecessary-enumerable-toArray-calls.php', __DIR__ . '/data/unnecessary-enumerable-toArray-calls-fixed.php');
    }
}
