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

    public function variableName(): string
    {
        $with = 'with';

        return 'foo';
    }

    public function variableNameWithNull(): string
    {
        $with = 'with';

        return 'bar';
    }

    public function stringName(): string
    {
        return 'foo';
    }
}
