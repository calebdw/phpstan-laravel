<?php

declare(strict_types=1);

namespace Tests\Rules;

use CalebDW\PhpstanLaravel\Rules\ConfigAccessorRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<ConfigAccessorRule> */
class ConfigAccessorRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(ConfigAccessorRule::class);
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/config-accessor-rule.php'], [
            ["Config key 'auth.defaults.guard' is string, but 'array' requires an array.", 18],
            ["Config key 'auth.defaults.guard' is string, but 'collection' requires an array.", 19],
            ["Config key 'auth.password_timeout' is int, but 'string' requires a string.", 20],
            ["Config key 'auth.defaults.guard' is string, but 'integer' requires an integer.", 21],
            ["Config key 'auth.password_timeout' is int, but 'boolean' requires a boolean.", 22],
            ["Config key 'auth.password_timeout' is int, but 'float' requires a float.", 25],
            ["Config key 'values.float' is float, but 'integer' requires an integer.", 37],
            ["Config key 'values.boolean' is bool, but 'string' requires a string.", 38],
            ["Config key 'values.array' is array<int, string>, but 'boolean' requires a boolean.", 39],
            ["Config key 'auth.password_timeout' is int, but 'string' requires a string.", 57],
            ["Config key 'auth.defaults.guard' is string, but 'integer' requires an integer.", 63],
            ["Config key 'auth.password_timeout' is int, but 'float' requires a float.", 68],
            ["Config key 'auth.defaults.guard' is string, but 'boolean' requires a boolean.", 74],
        ]);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-accessor-rule.neon'];
    }
}
