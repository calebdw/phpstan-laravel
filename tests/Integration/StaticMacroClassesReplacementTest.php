<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPStan\Analyser\Analyser;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;

final class StaticMacroClassesReplacementTest extends PHPStanTestCase
{
    #[Test]
    public function it_can_replace_the_default_static_macro_classes(): void
    {
        $file     = __DIR__ . '/data/static-macro-replacement.php';
        $analyser = self::getContainer()->getByType(Analyser::class);
        $errors   = $analyser->analyse([$file], null, null, true)->getErrors();

        $this->assertCount(1, $errors);
        $this->assertSame('Static call to instance method Illuminate\Support\Str::plainClosureMacro().', $errors[0]->getMessage());
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-static-macro-replacement.neon'];
    }
}
