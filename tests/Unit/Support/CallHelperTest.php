<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Testing\TypeInferenceTestCase;

class CallHelperTest extends TypeInferenceTestCase
{
    public function testFunctionNamesRespectNamespaceResolution(): void
    {
        $helper = self::getContainer()->getByType(CallHelper::class);
        $names  = [];

        self::processFile(__DIR__ . '/data/function-names.php', static function (Node $node, Scope $scope) use ($helper, &$names): void {
            if (! ($node instanceof FuncCall)) {
                return;
            }

            $names[] = $helper->callNames($node, $scope);
        });

        self::assertSame([['view'], ['view'], ['FunctionNamesShadow\view'], ['view']], $names);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../phpstan-tests.neon'];
    }
}
