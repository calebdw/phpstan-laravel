<?php

declare(strict_types=1);

namespace Tests\Rules\data;

class UselessValueFunctionCall
{
    public function foo(): string
    {
        return value('foo');
    }

    public function variableName(): string
    {
        $value = 'value';

        return $value('foo');
    }

    public function namedArguments(): string
    {
        return value(number: 1, value: 'bar');
    }
}
