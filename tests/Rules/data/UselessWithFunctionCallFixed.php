<?php

declare(strict_types=1);

namespace Tests\Rules\data;

class UselessWithFunctionCall
{
    public function foo(): string
    {
        return 'foo';
    }

    public function bar(): string
    {
        return 'bar';
    }
}
