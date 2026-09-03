<?php

declare(strict_types=1);

namespace Tests\Rules\Data;

use App\User;

class ModelMake
{
    use ModelMakeTrait;

    public function make(): User
    {
        return new User();
    }

    public function makeStringClass(): User
    {
        $class = User::class;

        return new $class();
    }

    public function makeFromTrait(): User
    {
        return $this->makeInTrait();
    }
}
