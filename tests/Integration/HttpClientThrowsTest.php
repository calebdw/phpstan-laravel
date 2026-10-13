<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function range;

final class HttpClientThrowsTest extends PHPStanTestCase
{
    use AnalysesFile;

    #[Test]
    public function it_throws_an_exception_from_synchronous_requests(): void
    {
        $errors  = $this->analyse(__DIR__ . '/data/http-client-throws.php');
        $missing = static fn (int $line, string $method, string $exception = 'Exception'): string => $line . ': Method HttpClientThrows\\' . $method . '() throws checked exception ' . $exception . ' but it\'s missing from the PHPDoc @throws tag.';

        $this->assertSame([
            ...array_map(static fn ($l) => $missing($l, 'Requests::undeclared'), range(22, 29)),
            $missing(41, 'Requests::declaredConnection'),
            $missing(55, 'Requests::caughtRequest'),
            $missing(63, 'Requests::maybeAsync'),
            $missing(91, 'Requests::factory'),
            $missing(96, 'Requests::facade'),
            $missing(97, 'Requests::facade'),
            $missing(102, 'Requests::overridden', 'DomainException'),
            $missing(123, 'SyncRequest::fetch'),
        ], array_map(static fn ($e) => $e->getLine() . ': ' . $e->getMessage(), $errors));
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-http-client-throws.neon'];
    }
}
