<?php

declare(strict_types=1);

namespace Tests\Rules;

use CalebDW\PhpstanLaravel\Rules\UndefinedConfigNameRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<UndefinedConfigNameRule> */
class UndefinedConfigNameRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(UndefinedConfigNameRule::class);
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/undefined-config-name-rule.php'], [
            ['Disk [avatars] does not have a configured driver.', 30],
            ['Cache store [memcache] is not defined.', 31],
            ['Database connection [mysqll] not configured.', 32],
            ['The [rabbitmq] queue connection has not been configured.', 33],
            ['Mailer [mailgun] is not defined.', 34],
            ['Log [sentry] is not defined.', 35],
            ['Broadcast connection [socket] is not defined.', 36],
            ['Auth guard [sanctum] is not defined.', 37],
            ['Disk [avatars] does not have a configured driver.', 43],
            ['Cache store [memcache] is not defined.', 45],
            ['Log [sentry] is not defined.', 47],
            ['Database connection [mysql::replica] not configured.', 55],
            ['Database connection [mysqll] not configured.', 56],
            ['Cache store [memcache] is not defined.', 64],
            ['Disk [avatars] does not have a configured driver.', 70],
            ['Database connection [bar] not configured.', 72],
            ['Disk [avatars] does not have a configured driver.', 111],
            ['Cache store [missing] is not defined.', 114],
            ['Broadcast connection [missing] is not defined.', 117],
            ['The [missing] queue connection has not been configured.', 117],
            ['Disk [avatars] does not have a configured driver.', 123],
            ['Disk [avatars] does not have a configured driver.', 126],
            ['Broadcast connection [missing] is not defined.', 130],
            ['The [missing] queue connection has not been configured.', 130],
        ]);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../phpstan-tests.neon'];
    }
}
