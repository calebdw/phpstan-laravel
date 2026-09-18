<?php

declare(strict_types=1);

namespace ModelForwardingAttributeScope;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /** @param Builder<static> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }

    public function named(Builder $query): void
    {
    }
}

User::active();
(new User())->active();
(new User())->named(User::query());
