<?php

declare(strict_types=1);

namespace ValidationNullableParent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

use function PHPStan\Testing\assertType;

function testValidator(): void
{
    $issue36 = Validator::make([], [
        'info' => ['present', 'nullable', 'array'],
        'info.status' => ['string', 'nullable'],
    ]);
    assertType('array{info?: array{status?: string|null}|null}', $issue36->validated());

    $list = Validator::make([], [
        'ids' => 'nullable|list',
        'ids.*' => 'integer',
    ]);
    assertType('array{ids?: list<int|numeric-string>|null}', $list->validated());

    $boundaries = Validator::make([], [
        'author' => 'array',
        'author.name' => 'required|string',
        'body' => 'nullable|string',
    ]);
    assertType('array{author: array{name: string}, body?: string|null}', $boundaries->validated());
}

function testFormRequest(SkillRequest $request): void
{
    assertType('array{skill_set_ids?: array<int|string, int|numeric-string>|null, info?: array{status?: string|null}|null}', $request->validated());
    assertType('array<int|string, int|numeric-string>|null', $request->skill_set_ids);
}

class SkillRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'skill_set_ids' => ['nullable', 'array'],
            'skill_set_ids.*' => ['integer', 'exists:skill_sets,id'],
            'info' => ['present', 'nullable', 'array'],
            'info.status' => ['string', 'nullable'],
        ];
    }
}
