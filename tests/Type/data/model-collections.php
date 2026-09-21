<?php

namespace ModelCollectionsL1128;

use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use function PHPStan\Testing\assertType;

function test(): void
{
    assertType('ModelCollectionsL1128\UserCollection', User::all());
    assertType('ModelCollectionsL1128\UserCollection', User::query()->where('id', '>', 1)->get());
}

/** @param collection-of<User> $users */
function testCollectionOf(Collection $users): void
{
    assertType('ModelCollectionsL1128\UserCollection', $users);
}

#[CollectedBy(UserCollection::class)]
class User extends Model
{

}

/** @extends Collection<int, User> */
class UserCollection extends Collection
{

}
