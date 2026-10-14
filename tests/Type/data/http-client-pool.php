<?php

namespace HttpClientPool;

use Illuminate\Http\Client\Batch;
use Illuminate\Http\Client\Pool;

use function PHPStan\Testing\assertType;

function test(Pool $pool, Batch $batch): void
{
    assertType('Illuminate\Http\Client\PendingRequest<true>', $pool->as('a'));
    assertType('Illuminate\Http\Client\PendingRequest<true>', $pool->newRequest());
    assertType('Illuminate\Http\Client\Promises\LazyPromise', $pool->get('localhost'));
    assertType('Illuminate\Http\Client\Promises\LazyPromise', $pool->as('a')->post('localhost'));

    assertType('Illuminate\Http\Client\PendingRequest<true>', $batch->as('a'));
    assertType('Illuminate\Http\Client\PendingRequest<true>', $batch->newRequest());
    assertType('Illuminate\Http\Client\Promises\LazyPromise', $batch->get('localhost'));
    assertType('Illuminate\Http\Client\Promises\LazyPromise', $batch->as('a')->post('localhost'));
}
