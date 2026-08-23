<?php

declare(strict_types=1);

namespace Tests\Rules\Data;

use App\User;

class ModelMakeSelf extends User
{
    public static function makeSelf(): self
    {
        return self::make();
    }

    public static function makeStatic(): static
    {
        return static::make();
    }

    public static function makeParent(): User
    {
        return parent::make();
    }
}
