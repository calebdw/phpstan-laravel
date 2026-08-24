<?php

namespace ModelMethods;

use App\User;

use function PHPStan\Testing\assertType;

function test(User $user): void
{
    assertType('App\User', User::createOrRestore(['id' => 1]));

    assertType('array{name: string, email: string}', $user->only('name', 'email'));
    assertType('array{name: string, email: string}', $user->only(['name', 'email']));
    assertType('array{name: string, nonexistent: null}', $user->only(['name', 'nonexistent']));
    assertType('array<string, mixed>', $user->only(['name', $foo]));

    // getAttribute() does not split on dots, so a dotted key is looked up whole
    // and misses, exactly as it does at runtime.
    assertType("array{'meta.a': null}", $user->only(['meta.a']));

    $columns = ['name', 'email'];
    $foo = 'nonexistent';
    assertType('array{name: string, email: string}', $user->only(...$columns));
    assertType('array{name: string, email: string}', $user->only($columns));
    assertType('array{nonexistent: null}', $user->only($foo));
    assertType(
        'array{name: string, email: string, nonexistent: null}',
        $user->only([...$columns, $foo]),
    );
}
