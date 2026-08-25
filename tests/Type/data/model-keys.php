<?php

declare(strict_types=1);

namespace ModelKeys;

use App\UlidModel;
use App\User;
use App\UuidModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

/**
 * @param Collection<int, User> $userCollection
 * @param Collection<int, UuidModel> $uuidCollection
 * @param Collection<string, User> $keyedUserCollection
 */
function test(
    Model $model,
    Collection $userCollection,
    Collection $uuidCollection,
    Collection $keyedUserCollection,
): void {
    assertType('int|string|null', $model->getKey());
    assertType('int|null', (new User())->getKey());
    assertType('string|null', (new UuidModel())->getKey());
    assertType('string|null', (new UlidModel())->getKey());

    assertType('array<int, int|null>', $userCollection->modelKeys());
    assertType('array<int, string|null>', $uuidCollection->modelKeys());
    assertType('array<string, int|null>', $keyedUserCollection->modelKeys());
}
