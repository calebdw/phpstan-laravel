<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;

final class StaticMacroClassesReplacementTest extends PHPStanTestCase
{
    use AnalysesFile;

    #[Test]
    public function it_can_replace_the_default_static_macro_classes(): void
    {
        $errors = $this->analyse(__DIR__ . '/data/static-macro-replacement.php');

        $this->assertCount(1, $errors);
        $this->assertSame('Static call to instance method Illuminate\Support\Str::plainClosureMacro().', $errors[0]->getMessage());
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-static-macro-replacement.neon'];
    }
}
