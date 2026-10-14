<?php

declare(strict_types=1);

namespace HttpClientThrows;

use DomainException;
use Exception;
use Illuminate\Http\Client\Batch;
use Illuminate\Http\Client\BatchInProgressException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class Requests
{
    public function undeclared(PendingRequest $request): void
    {
        $request->get('localhost');
        $request->head('localhost');
        $request->post('localhost');
        $request->patch('localhost');
        $request->put('localhost');
        $request->delete('localhost');
        $request->send('GET', 'localhost');
    }

    /** @throws Exception */
    public function declared(PendingRequest $request): void
    {
        $request->post('localhost');
    }

    /** @throws ConnectionException */
    public function declaredConnection(PendingRequest $request): void
    {
        $request->post('localhost');
    }

    public function caught(PendingRequest $request): void
    {
        try {
            $request->send('GET', 'localhost');
        } catch (Exception) {
        }
    }

    public function caughtRequest(PendingRequest $request): void
    {
        try {
            $request->throw()->get('localhost');
        } catch (RequestException) {
        }
    }

    /** @param PendingRequest<bool> $request */
    public function maybeAsync(PendingRequest $request): void
    {
        $request->get('localhost');
    }

    public function async(PendingRequest $request): void
    {
        $request->async()->get('localhost');
        $request->async()->send('GET', 'localhost');
        Http::async()->post('localhost');
    }

    /** @return list<mixed> */
    public function pool(Pool $pool): array
    {
        return [$pool->get('localhost'), $pool->as('a')->post('localhost'), $pool->newRequest()->send('GET', 'localhost')];
    }

    /**
     * @return list<mixed>
     *
     * @throws BatchInProgressException
     */
    public function batch(Batch $batch): array
    {
        return [$batch->get('localhost'), $batch->as('a')->post('localhost'), $batch->newRequest()->send('GET', 'localhost')];
    }

    public function factory(Factory $http): void
    {
        $http->put('localhost');
    }

    public function facade(): void
    {
        Http::get('localhost');
        Http::acceptJson()->delete('localhost');
    }

    public function overridden(OverriddenRequest $request): void
    {
        $request->get('localhost');
    }
}

/** @extends PendingRequest<true> */
final class AsyncRequest extends PendingRequest
{
    public function fetch(): void
    {
        parent::get('localhost');
        self::get('localhost');
        static::get('localhost');
        PendingRequest::get('localhost');
    }
}

/** @extends PendingRequest<false> */
final class SyncRequest extends PendingRequest
{
    public function fetch(): void
    {
        PendingRequest::get('localhost');
    }
}

final class OverriddenRequest extends PendingRequest
{
    /**
     * @param array<string, mixed>|string|null $query
     *
     * @throws DomainException
     */
    public function get(string $url, $query = null): never
    {
        throw new DomainException();
    }
}
