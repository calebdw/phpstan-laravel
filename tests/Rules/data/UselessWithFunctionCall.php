<?php

declare(strict_types=1);

namespace Tests\Rules\data;

class UselessWithFunctionCall
{
    public function foo(): string
    {
        return with('foo');
    }

    public function bar(): string
    {
        return with('bar', null);
    }

    public function variableName(): string
    {
        $with = 'with';

        return $with('foo');
    }

    public function variableNameWithNull(): string
    {
        $with = 'with';

        return $with('bar', null);
    }

    public function stringName(): string
    {
        return ('with')('foo');
    }
}
