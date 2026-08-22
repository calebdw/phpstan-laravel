<?php

declare(strict_types=1);

namespace FormRequest;

use App\User;
use Illuminate\Foundation\Http\FormRequest;

use function PHPStan\Testing\assertType;

function test(FormRequest $request, AuthedRequest $authedRequest): void
{
    assertType('Illuminate\Support\ValidatedInput', $request->safe());
    assertType('array{key: mixed}', $request->safe(['key']));
    assertType('array<string, mixed>', $request->validated());

    // A narrowed user() override is respected; the base returns the union.
    assertType('App\User', $authedRequest->user());
    assertType('App\Admin|App\User|null', $request->user());
}

/** Narrows user() to a concrete model. */
class AuthedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return parent::user() instanceof User;
    }

    public function user($guard = null): User
    {
        $user = parent::user($guard);

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
