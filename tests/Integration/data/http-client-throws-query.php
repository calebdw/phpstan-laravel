<?php

declare(strict_types=1);

namespace HttpClientThrowsQuery;

use Illuminate\Http\Client\PendingRequest;

final class Requests
{
    public function undeclared(PendingRequest $request): void
    {
        $request->query('localhost');
    }

    public function async(PendingRequest $request): void
    {
        $request->async()->query('localhost');
    }
}
