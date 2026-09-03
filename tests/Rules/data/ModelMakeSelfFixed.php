<?php

declare(strict_types=1);

namespace Tests\Rules\Data;

use App\User;

class ModelMakeSelf extends User
{
    public static function makeSelf(): self
    {
        return new self();
    }

    public static function makeStatic(): static
    {
        return new static();
    }

    public static function makeParent(): User
    {
        return new parent();
    }
}
