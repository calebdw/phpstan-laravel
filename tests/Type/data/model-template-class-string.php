<?php

namespace ModelTemplateClassString;

use App\Post;
use App\User;
use BuilderOfType\ModelWithNeverQuery;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/**
 * @template TModel of Model
 * @template TPost of Post
 * @param class-string<TModel> $modelClass
 * @param class-string<TPost> $postClass
 * @param TModel $model
 */
function test(string $modelClass, string $postClass, Model $model): void
{
    assertType('TModel of Illuminate\Database\Eloquent\Model (function ModelTemplateClassString\test(), argument)', $modelClass::findOrFail(1));
    assertType('TModel of Illuminate\Database\Eloquent\Model (function ModelTemplateClassString\test(), argument)|null', $modelClass::find(1));
    assertType('Illuminate\Database\Eloquent\Collection<int, TModel of Illuminate\Database\Eloquent\Model (function ModelTemplateClassString\test(), argument)>', $modelClass::find([1, 2]));
    assertType('TModel of Illuminate\Database\Eloquent\Model (function ModelTemplateClassString\test(), argument)', $modelClass::create([]));
    assertType('42|TModel of Illuminate\Database\Eloquent\Model (function ModelTemplateClassString\test(), argument)', $modelClass::findOr(1, fn () => 42));
    assertType('TModel of Illuminate\Database\Eloquent\Model (function ModelTemplateClassString\test(), argument)|null', $modelClass::where('id', 1)->first());
    assertType('Illuminate\Database\Eloquent\Collection<int, TModel of Illuminate\Database\Eloquent\Model (function ModelTemplateClassString\test(), argument)>', $modelClass::whereKey(1)->get());
    assertType('int<0, max>', $modelClass::count());
    assertType('TModel of Illuminate\Database\Eloquent\Model (function ModelTemplateClassString\test(), argument)', $model::findOrFail(1));
    assertType('TPost of App\Post (function ModelTemplateClassString\test(), argument)', $postClass::findOrFail(1));
    assertType('App\PostBuilder<TPost of App\Post (function ModelTemplateClassString\test(), argument)>', $postClass::where('id', 1));
}

/**
 * @template TNeverQuery of ModelWithNeverQuery
 * @param class-string<TNeverQuery> $neverQueryClass
 * @param class-string<Post>|class-string<User> $postOrUserClass
 */
function receivers(string $neverQueryClass, string $postOrUserClass): void
{
    assertType('TNeverQuery of BuilderOfType\ModelWithNeverQuery (function ModelTemplateClassString\receivers(), argument)', $neverQueryClass::findOrFail(1));
    assertType('Illuminate\Database\Eloquent\Collection<int, App\Post>|Illuminate\Database\Eloquent\Collection<int, App\User>', $postOrUserClass::get());
}

/**
 * @template TModel of User|Post
 * @param class-string<TModel> $modelClass
 */
function unionBound(string $modelClass): void
{
    assertType('TModel of App\Post|App\User (function ModelTemplateClassString\unionBound(), argument)', $modelClass::findOrFail(1));
    assertType('TModel of App\Post|App\User (function ModelTemplateClassString\unionBound(), argument)', $modelClass::create([]));
    assertType('Illuminate\Database\Eloquent\Collection<int, TModel of App\Post|App\User (function ModelTemplateClassString\unionBound(), argument)>', $modelClass::find([1, 2]));
    assertType('Illuminate\Database\Eloquent\Collection<int, TModel of App\Post|App\User (function ModelTemplateClassString\unionBound(), argument)>', $modelClass::whereKey(1)->get());
}

class CustomModel extends Model
{
    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes = []): string
    {
        return 'custom';
    }
}

/**
 * @template TModel of CustomModel
 * @param class-string<TModel> $modelClass
 */
function custom(string $modelClass): void
{
    assertType('string', $modelClass::create([]));
}
