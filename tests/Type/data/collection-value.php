<?php

declare(strict_types=1);

namespace CollectionValue;

use App\Post;
use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param  EloquentCollection<int, User>  $users
 * @param  Collection<int, array{name: string, role: string}>  $rows
 * @param  EloquentCollection<int, Post>  $posts
 * @param  LazyCollection<int, User>  $lazyUsers
 */
function test(
    EloquentCollection $users,
    Collection $rows,
    EloquentCollection $posts,
    LazyCollection $lazyUsers,
): void {
    assertType('string|null', $users->value('name'));
    assertType('int|null', $users->value('id'));
    assertType('string|null', $rows->value('name'));
    assertType('string|null', $posts->value('user.name'));
    assertType('string|null', $lazyUsers->value('email'));

    assertType('string', $users->value('name', 'anonymous'));
    assertType('int|false', $users->value('id', false));
    assertType('string', $users->value('name', fn () => 'anonymous'));
}
