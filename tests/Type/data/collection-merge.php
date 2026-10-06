<?php

namespace CollectionMerge;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

/**
 * @param Collection<5|9, string>           $narrowInt
 * @param Collection<int<0, 3>, string>     $ranged
 * @param Collection<'a'|'b', string>       $narrowString
 * @param Collection<int, string>           $ints
 * @param Collection<string, string>        $strings
 * @param LazyCollection<int<0, 3>, string> $lazy
 */
function keys(
    Collection $narrowInt,
    Collection $ranged,
    Collection $narrowString,
    Collection $ints,
    Collection $strings,
    LazyCollection $lazy,
): void {
    // array_merge() renumbers integer keys, so a key type narrower than int
    // cannot survive the merge
    assertType('Illuminate\Support\Collection<int, string>', $narrowInt->merge([1 => 'b']));
    assertType('Illuminate\Support\Collection<int, string>', $narrowInt->mergeRecursive([1 => 'b']));
    assertType('Illuminate\Support\Collection<int, string>', $ranged->merge([7 => 'b']));
    assertType('Illuminate\Support\LazyCollection<int, string>', $lazy->merge([7 => 'b']));

    // string keys are kept, and the incoming ones are not constrained to the
    // keys already there
    assertType('Illuminate\Support\Collection<string, string>', $narrowString->merge(['c' => 'z']));
    assertType('Illuminate\Support\Collection<string, string>', $strings->merge(['k' => 'b']));

    // merging string keys into an integer-keyed collection leaves both
    assertType('Illuminate\Support\Collection<int|string, string>', $ints->merge(['k' => 'b']));
}

/** @param Collection<int, User> $users */
function narrowKeysFromTheExtensions(Collection $users): void
{
    // partition() keys its result int<0, 1>, which merging has to widen
    $partitioned = $users->partition(fn (User $u) => true);

    assertType(
        'Illuminate\Support\Collection<int<0, 1>, Illuminate\Support\Collection<int, App\User>>',
        $partitioned,
    );
    assertType(
        'Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\User>>',
        $partitioned->merge($users->partition(fn (User $u) => false)),
    );

    // and mapToGroups() keys it by whatever the callback returned
    $grouped = $users->mapToGroups(fn (User $u) => [1 => $u]);

    assertType('Illuminate\Support\Collection<1, Illuminate\Support\Collection<int, App\User>>', $grouped);
    assertType(
        'Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\User>>',
        $grouped->merge($users->mapToGroups(fn (User $u) => [2 => $u])),
    );
}
