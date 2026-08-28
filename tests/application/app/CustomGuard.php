<?php

declare(strict_types=1);

namespace App;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Registered with Auth::extend() in tests/bootstrap.php.
 *
 * Deliberately not a framework guard: the extension can only know this class
 * by resolving the guard from the container.
 */
class CustomGuard implements StatefulGuard
{
    use GuardHelpers;

    public function __construct(UserProvider $provider)
    {
        $this->provider = $provider;
    }

    public function user(): Authenticatable|null
    {
        return $this->user;
    }

    /** @param array<string, mixed> $credentials */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $credentials
     * @param bool                  $remember
     */
    public function attempt(array $credentials = [], $remember = false): bool
    {
        return false;
    }

    /** @param array<string, mixed> $credentials */
    public function once(array $credentials = []): bool
    {
        return false;
    }

    /** @param bool $remember */
    public function login(Authenticatable $user, $remember = false): void
    {
        $this->setUser($user);
    }

    /**
     * @param mixed $id
     * @param bool  $remember
     */
    public function loginUsingId($id, $remember = false): Authenticatable|false
    {
        return false;
    }

    /** @param mixed $id */
    public function onceUsingId($id): Authenticatable|false
    {
        return false;
    }

    public function viaRemember(): bool
    {
        return false;
    }

    public function logout(): void
    {
        $this->user = null;
    }

    public function customGuardMethod(): int
    {
        return 5;
    }
}
