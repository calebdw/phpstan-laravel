<?php

declare(strict_types=1);

namespace Tests\Rules\data;

class CorrectValueFunctionCall
{
    public function foo(): string
    {
        return value(static function (int $foo) {
            return 'foo';
        });
    }
}
