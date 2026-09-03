<?php

declare(strict_types=1);

namespace Tests\Rules;

use CalebDW\PhpstanLaravel\Rules\UndefinedCommandInputRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<UndefinedCommandInputRule> */
class UndefinedCommandInputRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(UndefinedCommandInputRule::class);
    }

    public function testRule(): void
    {
        $this->analyse([
            __DIR__ . '/../application/app/Console/Commands/FooCommand.php',
            __DIR__ . '/../application/app/Console/Commands/BarCommand.php',
            __DIR__ . '/../application/app/Console/Commands/BazCommand.php',
        ], [
            [
                'Command "foo" does not have argument "foobar".',
                22,
            ],
            [
                'Command "foo" does not have option "foobar".',
                36,
            ],
            [
                'Command "foo" does not have argument "foobar".',
                50,
            ],
            [
                'Command "foo" does not have option "foobar".',
                50,
            ],
            [
                'Command "foo" does not have argument "missing".',
                51,
            ],
            [
                'Command "foo" does not have option "missing".',
                52,
            ],
        ]);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/phpstan-rules.neon',
        ];
    }
}
