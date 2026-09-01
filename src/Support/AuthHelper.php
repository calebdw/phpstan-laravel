<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Str;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Throwable;

use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function is_object;
use function is_string;

final class AuthHelper
{
    /** @var array<string, Type> */
    private array $guardTypes = [];

    /** @var array<string, list<string>> */
    private array $models = [];

    private string|false|null $defaultGuard = false;

    /** @var list<string>|false */
    private array|false $configuredGuards = false;

    private Type $defaultGuardType;

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private ContainerHelper $containerHelper,
    ) {
        $this->defaultGuardType = TypeCombinator::intersect(
            new ObjectType(Guard::class),
            new ObjectType(StatefulGuard::class),
        );
    }

    public function getGuardTypeFromArg(Arg|null $arg, Scope $scope): Type
    {
        if ($arg === null) {
            return $this->getGuardType();
        }

        $type   = $scope->getType($arg->value);
        $guards = $type->getConstantStrings();

        if ($guards === []) {
            return $type->isNull()->yes() ? $this->getGuardType() : $this->defaultGuardType;
        }

        $types = array_map(
            fn (ConstantStringType $guard): Type => $this->getGuardType($guard->getValue()),
            $guards,
        );

        if (! $type->isNull()->no()) {
            $types[] = $this->getGuardType();
        }

        return TypeCombinator::union(...$types);
    }

    /**
     * Returns the guard a call such as `auth('web')->user()` is scoped to, or
     * null when the guard is not statically known.
     *
     * @return list<string>|null
     */
    public function getGuardFromCall(MethodCall $methodCall, Scope $scope): array|null
    {
        $var = $methodCall->var;

        if (! $var instanceof StaticCall && ! $var instanceof MethodCall && ! $var instanceof FuncCall) {
            return null;
        }

        return $this->getGuardsFromArg($var->getArg('guard', 0) ?? $var->getArg('name', 0), $scope);
    }

    /** @return list<string>|null */
    public function getGuardsFromArg(Arg|null $arg, Scope $scope): array|null
    {
        if ($arg === null) {
            return null;
        }

        $type    = $scope->getType($arg->value);
        $strings = $type->getConstantStrings();

        if ($strings === []) {
            if (! $type->isNull()->yes()) {
                return null;
            }

            $default = $this->getDefaultGuard();

            return $default === null ? null : [$default];
        }

        $guards = array_map(static fn (ConstantStringType $string): string => $string->getValue(), $strings);

        if (! $type->isNull()->no()) {
            $default = $this->getDefaultGuard();

            if ($default === null) {
                return null;
            }

            $guards[] = $default;
        }

        return array_values(array_unique($guards));
    }

    /**
     * Returns the type of the given guard, null being the default guard.
     *
     * @param list<string>|string|null $guards
     */
    public function getGuardType(array|string|null $guards = null): Type
    {
        if (! is_array($guards)) {
            return $this->getSingleGuardType($guards);
        }

        $types = [];

        foreach ($guards as $guard) {
            $types[] = $this->getSingleGuardType($guard);
        }

        return $types === [] ? $this->defaultGuardType : TypeCombinator::union(...$types);
    }

    /**
     * Returns the type of the given guard's user, or null when the application
     * configures no auth model for it.
     *
     * A null guard covers every configured guard, which is the best that can be
     * said for a call that does not name one.
     *
     * @param list<string>|string|null $guards
     */
    public function getUserType(array|string|null $guards = null, bool $nullable = false): Type|null
    {
        $models = $this->getModels($guards);

        if ($models === []) {
            return null;
        }

        $type = TypeCombinator::union(...array_map(static fn ($m) => new ObjectType($m), $models));

        return $nullable ? TypeCombinator::addNull($type) : $type;
    }

    /**
     * @param list<string>|string|null $guard
     *
     * @return list<string>
     */
    public function getModels(array|string|null $guard = null): array
    {
        if ($guard === null) {
            $guard = $this->getConfiguredGuards();
        }

        if (! is_array($guard)) {
            return $this->models[$guard] ??= $this->resolveModels($guard);
        }

        $models = [];

        foreach ($guard as $name) {
            foreach ($this->getModels($name) as $model) {
                if (in_array($model, $models, true)) {
                    continue;
                }

                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * The guard is resolved from the container rather than mapped from its
     * driver name. A guard registered with Auth::extend() takes precedence
     * over the manager's own create*Driver() method, so only the container
     * knows what an application really gets back - including a guard that
     * decorates or replaces a framework one.
     */
    private function getResolvedGuardType(string|null $guard): Type|null
    {
        $manager = $this->containerHelper->resolve(Factory::class);

        if (! $manager instanceof Factory) {
            return null;
        }

        try {
            $instance = $manager->guard($guard);
        } catch (Throwable) {
            return null;
        }

        if (! $this->reflectionProvider->hasClass($instance::class)) {
            return null;
        }

        return new ObjectType($instance::class);
    }

    /**
     * Falls back to the declared return type of the manager's create*Driver()
     * method, which still covers the framework's own drivers when the guard
     * itself cannot be instantiated.
     */
    private function getCreatorReturnType(string|null $guard): Type|null
    {
        $config  = $this->containerHelper->getConfigRepository();
        $manager = $this->containerHelper->resolve(Factory::class);

        if ($config === null || ! is_object($manager) || ! $this->reflectionProvider->hasClass($manager::class)) {
            return null;
        }

        $guard ??= $this->getDefaultGuard();

        if (! is_string($guard)) {
            return null;
        }

        $driver = $config->get('auth.guards.' . $guard . '.driver');

        if (! is_string($driver)) {
            return null;
        }

        $reflection = $this->reflectionProvider->getClass($manager::class);
        $creator    = 'create' . Str::studly($driver) . 'Driver';

        if (! $reflection->hasNativeMethod($creator)) {
            return null;
        }

        $type = ParametersAcceptorSelector::selectFromTypes(
            [],
            $reflection->getNativeMethod($creator)->getVariants(),
            false,
        )->getReturnType();

        // Creators without a declared return type are of no use here.
        return $type->getObjectClassNames() === [] ? null : $type;
    }

    private function getSingleGuardType(string|null $guard): Type
    {
        $key = $guard ?? '';

        return $this->guardTypes[$key] ??= $this->getResolvedGuardType($guard)
            ?? $this->getCreatorReturnType($guard)
            ?? $this->defaultGuardType;
    }

    /** @return list<string> */
    private function resolveModels(string $guard): array
    {
        $config    = $this->containerHelper->getConfigRepository();
        $guards    = $config?->get('auth.guards');
        $providers = $config?->get('auth.providers');

        if (! is_array($guards) || ! is_array($providers)) {
            return [];
        }

        $provider  = $guards[$guard]['provider'] ?? null;
        $authModel = is_string($provider) ? ($providers[$provider]['model'] ?? null) : null;

        return is_string($authModel) ? [$authModel] : [];
    }

    /** @return list<string> */
    private function getConfiguredGuards(): array
    {
        if ($this->configuredGuards !== false) {
            return $this->configuredGuards;
        }

        $guards = $this->containerHelper->getConfigRepository()?->get('auth.guards');

        return $this->configuredGuards = is_array($guards) ? array_keys($guards) : [];
    }

    private function getDefaultGuard(): string|null
    {
        if ($this->defaultGuard !== false) {
            return $this->defaultGuard;
        }

        $guard = $this->containerHelper->getConfigRepository()?->get('auth.defaults.guard');

        return $this->defaultGuard = is_string($guard) ? $guard : null;
    }
}
