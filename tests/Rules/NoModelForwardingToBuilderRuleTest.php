<?php

declare(strict_types=1);

namespace Tests\Rules;

use CalebDW\PhpstanLaravel\Rules\NoModelForwardingToBuilderRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use function strtr;

/** @extends RuleTestCase<NoModelForwardingToBuilderRule> */
class NoModelForwardingToBuilderRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoModelForwardingToBuilderRule();
    }

    public function testRule(): void
    {
        $message = static fn (string $name) => strtr(
            "Method [:name] is forwarded to a Builder instance, which is not allowed.\n    💡 Use [:::name()], [::query()->:name()] or [->newQuery()->:name()] instead.",
            [':name' => $name],
        );

        $this->analyse([__DIR__ . '/data/NoModelForwardingToBuilderInstance.php'], [
            [$message('first'), 5],
            [$message('get'), 6],
            [$message('find'), 7],
            [$message('paginate'), 8],
            [$message('where'), 9],
            [$message('take'), 10],
            [$message('max'), 11],
            [$message('with'), 12],
            [$message('first'), 14],
            [$message('with'), 15],
        ]);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../phpstan-tests.neon'];
    }
}
