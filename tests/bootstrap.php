<?php

declare(strict_types=1);

use App\CustomGuard;
use App\FirstCustomQueryBuilder;
use App\Importer;
use App\SecondCustomQueryBuilder;
use Illuminate\Auth\RequestGuard;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

Builder::macro('globalCustomMacro', static function (string $arg = 'foobar', int $b = 5): string {
    return $arg;
});

Builder::macro('modelBoundMacro', function (): string {
    return $this->getModel()::class;
});

\Illuminate\Database\Query\Builder::macro('globalCustomDatabaseQueryMacro', static function (string $arg = 'foobar', int $b = 5): string {
    return $arg;
});

FirstCustomQueryBuilder::macro('customScalar', fn (int $v): int => $v);
SecondCustomQueryBuilder::macro('customScalar', fn (int $v): string => (string) $v);
FirstCustomQueryBuilder::macro('count', fn (): string => 'shadowed');

Route::macro('facadeMacro', static function (): int {
    return 5;
});

Route::macro('facadePlainClosureMacro', function (): int {
    return 5;
});

SessionGuard::macro('sessionGuardMacro', static function (): int {
    return 5;
});

RequestGuard::macro('requestGuardMacro', static function (): int {
    return 5;
});

Str::macro('trimMacro', 'trim');
Str::macro('asciiAliasMacro', Str::class . '::ascii');

foreach ([Arr::class, Str::class, Number::class, Benchmark::class, Rule::class] as $staticMacroClass) {
    $staticMacroClass::macro('plainClosureMacro', function (): string {
        return 'x';
    });
}

// Both closure styles on one class, so each call form can be exercised against
// each. The closure style is how a macro declares which form it is for.
Collection::macro('staticClosureMacro', static function (): string {
    return 'x';
});
Collection::macro('plainClosureMacro', function (): string {
    return 'x';
});


Cache::macro('rememberIf', static fn ($cond, $key, $ttl, $callback): mixed => $cond ? Cache::remember($key, $ttl, $callback) : $callback());

Importer::macro('foo', fn () => $this);

class CustomCollectionMacro
{
    public function registerMacro(): void
    {
        Collection::macro('customCollectionMacro', [$this, 'customMacro']);
        Collection::macro('customCollectionMacroString', [self::class, 'customMacroString']);
    }

    public function customMacro(): string
    {
        return 'customMacro';
    }

    public function customMacroString(): string
    {
        return 'customMacroString';
    }
}

(new CustomCollectionMacro())->registerMacro();

enum FooEnum: string
{
    case Foo = 'foo';
}

Builder::macro('macroWithEnumDefaultValue', static function (string $arg = 'foobar', $b = FooEnum::Foo): string {
    return $arg;
});

Auth::extend('custom', static fn ($app, $name, array $config): CustomGuard => new CustomGuard(
    Auth::createUserProvider($config['provider']),
));
