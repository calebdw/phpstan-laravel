<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Http\Client\PendingRequest;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\Test;

use function array_fill;
use function array_map;
use function range;

final class HttpClientThrowsTest extends PHPStanTestCase
{
    use AnalysesFile;

    #[Test]
    public function it_preserves_request_exceptions_including_async_preparation(): void
    {
        $errors  = $this->analyse(__DIR__ . '/data/http-client-throws.php');
        $missing = static fn (int $line, string $method, string $exception = 'Exception'): string => $line . ': Method HttpClientThrows\\' . $method . '() throws checked exception ' . $exception . ' but it\'s missing from the PHPDoc @throws tag.';

        $this->assertSame([
            ...array_map(static fn ($l) => $missing($l, 'Requests::undeclared'), range(22, 28)),
            $missing(40, 'Requests::declaredConnection'),
            $missing(54, 'Requests::caughtRequest'),
            $missing(62, 'Requests::maybeAsync'),
            ...array_map(static fn ($l) => $missing($l, 'Requests::async'), range(67, 69)),
            ...array_fill(0, 3, $missing(75, 'Requests::pool')),
            ...array_fill(0, 3, $missing(85, 'Requests::batch')),
            $missing(90, 'Requests::factory'),
            $missing(95, 'Requests::facade'),
            $missing(96, 'Requests::facade'),
            $missing(101, 'Requests::overridden', 'DomainException'),
            ...array_map(static fn ($l) => $missing($l, 'AsyncRequest::fetch'), range(110, 113)),
            $missing(122, 'SyncRequest::fetch'),
        ], array_map(static fn ($e) => $e->getLine() . ': ' . $e->getMessage(), $errors));
    }

    #[Test]
    #[RequiresMethod(PendingRequest::class, 'query')]
    public function it_throws_an_exception_from_query_requests(): void
    {
        $errors = $this->analyse(__DIR__ . '/data/http-client-throws-query.php');

        $this->assertSame([
            '13: Method HttpClientThrowsQuery\\Requests::undeclared() throws checked exception Exception but it\'s missing from the PHPDoc @throws tag.',
            '18: Method HttpClientThrowsQuery\\Requests::async() throws checked exception Exception but it\'s missing from the PHPDoc @throws tag.',
        ], array_map(static fn ($e) => $e->getLine() . ': ' . $e->getMessage(), $errors));
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-http-client-throws.neon'];
    }
}
