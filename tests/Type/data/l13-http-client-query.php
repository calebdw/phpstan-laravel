<?php

namespace HttpClientQuery;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

use function PHPStan\Testing\assertType;

function test(PendingRequest $request): void
{
    assertType('Illuminate\Http\Client\Response', $request->query('localhost'));
    assertType('Illuminate\Http\Client\Promises\LazyPromise', $request->async()->query('localhost'));
    assertType('Illuminate\Http\Client\Promises\LazyPromise', Http::async()->query('localhost'));
}
