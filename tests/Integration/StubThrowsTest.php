<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function range;

final class StubThrowsTest extends PHPStanTestCase
{
    use AnalysesFile;

    #[Test]
    public function stubbed_calls_preserve_checked_exceptions(): void
    {
        $errors  = $this->analyse(__DIR__ . '/data/stub-throws.php');
        $missing = static fn (int $line, string $exception): string => $line . ': Function StubThrows\\undeclared() throws checked exception ' . $exception . ' but it\'s missing from the PHPDoc @throws tag.';

        $this->assertSame([
            ...array_map(static fn ($l) => $missing($l, 'Illuminate\\Validation\\ValidationException'), range(24, 30)),
            $missing(31, 'Illuminate\\Database\\Eloquent\\ModelNotFoundException<TModel of Illuminate\\Database\\Eloquent\\Model>'),
            $missing(32, 'LogicException'),
            $missing(33, 'Illuminate\\Database\\Eloquent\\ModelNotFoundException<TRelatedModel of Illuminate\\Database\\Eloquent\\Model>'),
            ...array_map(static fn ($l) => $missing($l, 'Illuminate\\Database\\UniqueConstraintViolationException'), range(34, 36)),
            $missing(37, 'Illuminate\\Support\\ItemNotFoundException'),
            $missing(37, 'Illuminate\\Support\\MultipleItemsFoundException'),
            ...array_map(static fn ($l) => $missing($l, 'InvalidArgumentException'), range(38, 42)),
            $missing(43, 'Illuminate\\Database\\Eloquent\\ModelNotFoundException<TModel of Illuminate\\Database\\Eloquent\\Model>'),
            $missing(43, 'Illuminate\\Database\\MultipleRecordsFoundException'),
        ], array_map(static fn ($e) => $e->getLine() . ': ' . $e->getMessage(), $errors));
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-http-client-throws.neon'];
    }
}
