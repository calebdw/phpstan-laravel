<?php

declare(strict_types=1);

namespace CollectionDuplicates;

use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

use function PHPStan\Testing\assertType;

/**
 * @param  Collection<int, User>  $users
 * @param  EloquentCollection<int, User>  $eloquent
 * @param  Collection<string, array{name: string, id: int}>  $rows
 */
function test(Collection $users, EloquentCollection $eloquent, Collection $rows): void
{
    assertType('Illuminate\Support\Collection<int, App\User>', $users->duplicates());
    assertType('Illuminate\Support\Collection<int, string>', $users->duplicates('email'));
    assertType('Illuminate\Support\Collection<int, int>', $users->duplicates(fn ($u) => $u->id));
    assertType('Illuminate\Support\Collection<int, string>', $users->duplicatesStrict('email'));

    assertType('Illuminate\Database\Eloquent\Collection<int, App\User>', $eloquent->duplicates());
    assertType('Illuminate\Database\Eloquent\Collection<int, string>', $eloquent->duplicates('email'));

    assertType('Illuminate\Support\Collection<string, string>', $rows->duplicates('name'));
    assertType('Illuminate\Support\Collection<string, int>', $rows->duplicates(fn ($r) => $r['id']));
}
