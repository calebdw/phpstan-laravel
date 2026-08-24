<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPStan\Analyser\Analyser;
use PHPStan\Analyser\Error;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;

final class StaticMacroClassesTest extends PHPStanTestCase
{
    #[Test]
    public function it_allows_configured_models_to_call_macros_statically(): void
    {
        $errors = $this->analyse(__DIR__ . '/data/static-model-macro.php');

        $this->assertNoErrors($errors);
    }

    #[Test]
    public function it_does_not_allow_native_instance_methods_to_be_called_statically(): void
    {
        $errors = $this->analyse(__DIR__ . '/data/static-model-native-method.php');

        $this->assertCount(1, $errors);
        $this->assertSame('Static call to instance method App\Post::user().', $errors[0]->getMessage());
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-static-model-macro.neon'];
    }

    /** @return list<Error> */
    private function analyse(string $file): array
    {
        $analyser = self::getContainer()->getByType(Analyser::class);

        return $analyser->analyse([$file], null, null, true)->getErrors();
    }
}
