<?php

declare(strict_types=1);

namespace Tests\Rules\data;

class UselessValueFunctionCall
{
    public function foo(): string
    {
        return 'foo';
    }
}
