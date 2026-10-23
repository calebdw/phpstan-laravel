<?php

namespace ModelPropertyFill;

use App\User;
use Illuminate\Database\Eloquent\Model;

function test(User $user): void
{
    $user->fill(['foo' => 'bar']);
    $user->forceFill(['foo' => 'bar'])->save();
    $user->fill(['name' => 'test']);
    $user->forceFill(['email' => 'test@example.com'])->save();
    $user->fill([]);
}

/** @param array<string, mixed> $attributes */
function dynamic(User $user, array $attributes): void
{
    $user->fill($attributes);
    $user->forceFill($attributes);
}

class FillOnModel extends Model
{
    protected $table = 'users';

    public function test(): void
    {
        $this->fill(['foo' => 'bar']);
        $this->forceFill(['foo' => 'bar']);
        $this->fill(['name' => 'test']);
    }
}
