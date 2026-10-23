<?php

namespace ModelFill;

use App\Post;
use App\User;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

function test(User $user, User|Post $model): void
{
    assertType('App\User', $user->fill(['name' => 'test']));
    assertType('App\User', $user->forceFill(['email' => 'test@example.com']));
    assertType('App\User', $user->fill([]));
    assertType('App\Post|App\User', $model->forceFill(['id' => 1]));
}

/**
 * @template TModel of Model
 * @param TModel $model
 * @param array<string, mixed> $attributes
 */
function generic(Model $model, array $attributes): void
{
    assertType('TModel of Illuminate\Database\Eloquent\Model (function ModelFill\generic(), argument)', $model->fill($attributes));
    assertType('TModel of Illuminate\Database\Eloquent\Model (function ModelFill\generic(), argument)', $model->forceFill($attributes));
}
