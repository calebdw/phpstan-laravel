<?php

declare(strict_types=1);

namespace GroupBy;

use App\Post;
use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Stringable;

use function PHPStan\Testing\assertType;

enum GroupKey: int
{
    case First  = 10;
    case Second = 20;
}

enum GroupLabel: string
{
    case Alpha = 'alpha';
    case Beta  = 'beta';
}

final class Grouped
{
    public GroupKey $key;
    public GroupLabel $label;
}

/**
 * @param  EloquentCollection<int, User>  $users
 * @param  EloquentCollection<int, Post>  $posts
 * @param  Collection<string, User>  $keyed
 * @param  LazyCollection<string, User>  $lazy
 */
function test(
    EloquentCollection $users,
    EloquentCollection $posts,
    Collection $keyed,
    LazyCollection $lazy,
): void {
    // One grouper, key resolved from the column.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy('name'));
    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy('id'));

    // Two and three groupers nest one level each.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>>', $users->groupBy(['name', 'id']));
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>>>', $users->groupBy(['name', 'id', 'email']));

    // Dotted access through a relation.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\Post>>', $posts->groupBy('user.name'));

    // Callables, including ones declaring no types.
    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(fn ($u) => $u->id));
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(function ($u) {
        return $u->name;
    }));

    // A bool group key is cast to int, a Stringable to string.
    assertType('Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(fn ($u) => $u->id > 5));
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(fn ($u): Stringable => str($u->name)));

    // A grouper returning an array files the item under several keys.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, App\User>>', $users->groupBy(fn ($u): array => [$u->name]));

    // Mixing a column and a callable across levels.
    assertType('Illuminate\Database\Eloquent\Collection<string, Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Collection<int, App\User>>>', $users->groupBy(['name', fn ($u) => $u->id]));

    // preserveKeys keeps the original keys on the innermost collection.
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<int, App\User>>', $keyed->groupBy('id'));
    assertType('Illuminate\Support\Collection<int, Illuminate\Support\Collection<string, App\User>>', $keyed->groupBy('id', true));
    assertType('Illuminate\Support\LazyCollection<int, Illuminate\Support\Collection<int, App\User>>', $lazy->groupBy('id'));
    assertType('Illuminate\Support\LazyCollection<string, Illuminate\Support\Collection<int, Illuminate\Support\Collection<string, App\User>>>', $lazy->groupBy(['name', 'id'], true));
}

/**
 * Keys are resolved precisely. A grouper returning a backed enum's value gives
 * the literal union, and an interpolated key gives the product of its parts.
 *
 * @param  Collection<int, Grouped>  $items
 */
function testEnumBackedKeys(Collection $items): void
{
    assertType('Illuminate\Support\Collection<10|20, Illuminate\Support\Collection<int, GroupBy\Grouped>>', $items->groupBy(fn (Grouped $i) => $i->key->value));
    assertType("Illuminate\\Support\\Collection<'alpha'|'beta', Illuminate\\Support\\Collection<int, GroupBy\\Grouped>>", $items->groupBy(fn (Grouped $i) => $i->label->value));
    assertType("Illuminate\\Support\\Collection<'10|alpha'|'10|beta'|'20|alpha'|'20|beta', GroupBy\\Grouped>", $items->keyBy(fn (Grouped $i) => "{$i->key->value}|{$i->label->value}"));

    // The enum itself goes through enum_value(), so the key stays a benevolent
    // int|string rather than being narrowed to one of them.
    assertType('Illuminate\Support\Collection<(int|string), Illuminate\Support\Collection<int, GroupBy\Grouped>>', $items->groupBy(fn (Grouped $i) => $i->key));

    assertType('Illuminate\Support\Collection<10|20, GroupBy\Grouped>', $items->keyBy(fn (Grouped $i) => $i->key->value));
    assertType('Illuminate\Support\Collection<int, GroupBy\GroupLabel>', $items->pluck('label'));
}

/**
 * Precision is worth keeping because it survives to the iteration site.
 *
 * @param  Collection<int, Grouped>  $items
 */
function testRefinedKeySurvivesIteration(Collection $items): void
{
    foreach ($items->keyBy(fn (Grouped $i) => "row-{$i->key->value}") as $key => $_) {
        assertType("'row-10'|'row-20'", $key);
    }
}

/**
 * Where a caller wants the widened type, use-site variance gives it without
 * the inference having to throw the precision away for everyone.
 *
 * @param  Collection<int, Grouped>  $items
 * @return Collection<covariant string, Grouped>
 */
function testCovariantAnnotationAcceptsALiteralKey(Collection $items): Collection
{
    return $items->keyBy(fn (Grouped $i) => "{$i->key->value}|{$i->label->value}");
}
