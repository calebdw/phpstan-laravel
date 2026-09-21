<?php

namespace FactoryOfType;

use App\Post;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/**
 * @param factory-of<User> $users
 * @param factory-of<Post> $posts
 * @param factory-of<User|Post> $union
 */
function test(Factory $users, Factory $posts, Factory $union): void
{
    assertType('Database\Factories\UserFactory', $users);
    assertType('Database\Factories\Post\PostFactory', $posts);
    assertType('Database\Factories\Post\PostFactory|Database\Factories\UserFactory', $union);

    assertType('Database\Factories\UserFactory', factoryFor(User::class));
    assertType('Database\Factories\Post\PostFactory', factoryFor(Post::class));
    assertType('Database\Factories\UserFactory', userFactory());
    assertType('App\User|Illuminate\Database\Eloquent\Collection<int, App\User>', $users->create());
}

/** @return factory-of<User> */
function userFactory(): Factory
{
    return User::factory();
}

/**
 * @template TModel of Model
 * @param class-string<TModel> $model
 * @return factory-of<TModel>
 */
function factoryFor(string $model)
{
    return $model::factory();
}
