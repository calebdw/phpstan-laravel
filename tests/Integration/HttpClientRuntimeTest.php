<?php

declare(strict_types=1);

namespace Tests\Integration;

use DomainException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpClientRuntimeTest extends TestCase
{
    #[Test]
    public function failed_responses_do_not_throw_by_default(): void
    {
        $http = (new Factory())->fake(['*' => Factory::response('', 500)]);

        self::assertSame(500, $http->get('https://example.test')->status());
    }

    #[Test]
    #[DataProvider('throwingConfigurations')]
    public function configured_requests_throw_request_exceptions(string $configuration): void
    {
        $http    = (new Factory())->fake(['*' => Factory::response('', 500)]);
        $request = $configuration === 'throw' ? $http->throw() : $http->retry(2, 0);

        $this->expectException(RequestException::class);

        $request->get('https://example.test');
    }

    /** @return iterable<string, array{string}> */
    public static function throwingConfigurations(): iterable
    {
        yield 'throw' => ['throw'];
        yield 'retry' => ['retry'];
    }

    #[Test]
    #[DataProvider('callbacks')]
    public function callback_exceptions_are_not_wrapped(string $callback): void
    {
        $http    = (new Factory())->fake(['*' => Factory::response('', 500)]);
        $request = match ($callback) {
            'throw' => $http->throw(static fn () => throw new DomainException()),
            'retry' => $http->retry(2, 0, static fn () => throw new DomainException()),
            'before' => $http->beforeSending(static fn () => throw new DomainException()),
            'after' => $http->afterResponse(static fn () => throw new DomainException()),
            'middleware' => $http->withRequestMiddleware(static fn () => throw new DomainException()),
        };

        $this->expectException(DomainException::class);

        $request->get('https://example.test');
    }

    /** @return iterable<string, array{string}> */
    public static function callbacks(): iterable
    {
        foreach (['throw', 'retry', 'before', 'after', 'middleware'] as $callback) {
            yield $callback => [$callback];
        }
    }

    #[Test]
    public function async_body_conversion_can_throw_before_returning_a_promise(): void
    {
        $http = (new Factory())->fake();
        $body = new class implements Arrayable {
            public function toArray(): never
            {
                throw new DomainException();
            }
        };

        $this->expectException(DomainException::class);

        $http->async()->post('https://example.test', $body);
    }
}
