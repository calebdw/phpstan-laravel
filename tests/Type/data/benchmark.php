<?php

namespace Benchmark;

use Illuminate\Support\Benchmark;

use function PHPStan\Testing\assertType;

function test(): void
{
    assertType('float', Benchmark::measure(fn () => 'Hello World'));
    assertType('array<0|1, float>', Benchmark::measure([fn () => 'Hello World', fn () => 'Hello World']));
    assertType('array<\'test1\'|\'test2\', float>', Benchmark::measure(['test1' => fn () => 'Hello World', 'test2' => fn () => 'Hello World']));
    assertType('array<100|\'test\', float>', Benchmark::measure(['test' => fn () => 'Hello World', 100 => fn () => 'Hello World']));
}
