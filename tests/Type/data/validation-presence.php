<?php

declare(strict_types=1);

namespace ValidationPresence;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\ExcludeIf;
use Stringable;

use function PHPStan\Testing\assertType;

function testValidator(): void
{
    $optional = Validator::make([], [
        'tasks' => 'array',
        'tasks.*.name' => 'required|string',
        'info' => 'required|array',
        'info.status' => 'nullable|string',
        'a.b' => 'required|array',
        'a.b.c' => 'string',
        'groups' => 'required|array',
        'groups.*' => 'array',
        'groups.*.name' => 'string',
        'settings' => ['required', Rule::array()],
        'settings.theme' => 'string',
        'draft' => 'integer',
        'title' => 'exclude_if:draft,1|required|string',
        'editor' => 'exclude_if:draft,1|array',
        'editor.name' => 'required|string',
    ]);
    assertType('array{tasks?: array<int|string, array{name: string}>, info?: array{status?: string|null}, a?: array{b?: array{c?: string}}, groups?: array<int|string, array{name?: string}>, settings?: array{theme?: string}, draft?: int|numeric-string, title?: string, editor?: array{name: string}}', $optional->validated());

    $required = Validator::make([], [
        'author.name' => 'required|string',
        'tags' => 'required|array',
        'tags.*' => 'string',
        'items' => 'required|array',
        'items.*.id' => 'required|integer',
        'meta' => 'required|array:a,b',
        'meta.a' => 'string',
        'rows' => 'required',
        'rows.*.id' => 'integer',
    ]);
    assertType('array{author: array{name: string}, tags: array<int|string, string>, items: array<int|string, array{id: int|numeric-string}>, meta: array{a?: string}, rows: array<int|string, array{id?: int|numeric-string}>}', $required->validated());
}

function testKeptThroughChildren(): void
{
    $kept = Validator::make([], [
        'draft' => 'integer',
        'm' => 'required|array',
        'm.*.*' => 'integer',
        'a' => 'required|array',
        'a.*.b.*.c' => 'required',
        'info' => 'required|array',
        'info.title' => 'exclude_if:draft,1|required|string',
        'r' => 'required|array',
        'r.*' => 'exclude_if:draft,1|string',
        'p' => ['required', fn ($attribute, $value, $fail) => null],
        'p.*.id' => 'integer',
        'notes' => 'required|array',
        'notes.title' => 'exclude_if:draft,1|string',
    ]);
    assertType('array{draft?: int|numeric-string, m: array<int|string, array<int|string, int|numeric-string>>, a: array<int|string, array{b?: array<int|string, array{c: string}>}>, info: array{title?: string}, r: array<int|string, string>, p: array<int|string, array{id?: int|numeric-string}>, notes?: array{title?: string}}', $kept->validated());
}

function testPresenceRules(bool $flag): void
{
    $present = Validator::make([], [
        'body' => ['present', 'nullable', 'string'],
        'gone' => 'missing',
        'maybe' => 'sometimes|present|string',
        'excluded' => 'exclude_if:flag,1|present|string',
        'conditional' => 'present_if:flag,1|string',
        'with' => 'required_with:body|string',
        'branch' => $flag ? 'present|string' : 'string',
    ]);
    assertType('array{body: string|null, gone?: mixed, maybe?: string, excluded?: string, conditional?: string, with?: string, branch?: string}', $present->validated());

    $accepted = Validator::make([], [
        'terms' => 'accepted',
        'optout' => ['declined'],
    ])->validated();
    assertType('true', array_key_exists('terms', $accepted));
    assertType('true', array_key_exists('optout', $accepted));
}

function testPresenceRequest(PresenceRequest $request): void
{
    $validated = $request->validated();
    assertType('string|null', $validated['body']);
    assertType('true', array_key_exists('body', $validated));
    assertType('true', array_key_exists('terms', $validated));
    assertType('true', array_key_exists('optout', $validated));
    assertType('bool', array_key_exists('maybe', $validated));
}

function testFormRequest(OptionalRequest $optional, RequiredRequest $required, KeptRequest $kept): void
{
    assertType('array{draft?: int|numeric-string, m: array<int|string, array<int|string, int|numeric-string>>, a: array<int|string, array{b?: array<int|string, array{c: string}>}>, info: array{title?: string}, r: array<int|string, string>, p: array<int|string, array{id?: int|numeric-string}>, notes?: array{title?: string}}', $kept->validated());
    assertType('array{tasks?: array<int|string, array{name: string}>, info?: array{status?: string|null}, a?: array{b?: array{c?: string}}, groups?: array<int|string, array{name?: string}>, settings?: array{theme?: string}, draft?: int|numeric-string, title?: string, editor?: array{name: string}}', $optional->validated());
    assertType('array{author: array{name: string}, tags: array<int|string, string>, items: array<int|string, array{id: int|numeric-string}>, meta: array{a?: string}, rows: array<int|string, array{id?: int|numeric-string}>}', $required->validated());
}

function testRuleObjects(bool $flag, RuleObjectRequest $request): void
{
    $objects = Validator::make([], [
        'ex' => [Rule::excludeIf($flag), 'required', 'string'],
        'exn' => [new ExcludeIf($flag), 'required', 'string'],
        'r' => ['required', 'array', Rule::excludeIf($flag)],
        'r.*' => 'string',
        'items' => ['required', new CustomRule()],
        'items.*.id' => 'integer',
        'opts' => ['required', Rule::array(['a', 'b'])],
        'opts.a' => 'string',
        'empty' => ['required', Rule::array([])],
        'empty.a' => 'string',
        'unknown' => ['required', new Stringy()],
        'unknown.a' => 'string',
    ]);
    assertType('array{ex?: string, exn?: string, r?: array<int|string, string>, items: array<int|string, array{id?: int|numeric-string}>, opts: array{a?: string}, empty?: array{a?: string}, unknown?: array{a?: string}}', $objects->validated());
    assertType('array{ex?: string, exn?: string, r?: array<int|string, string>, items: array<int|string, array{id?: int|numeric-string}>, opts: array{a?: string}, empty?: array{a?: string}, unknown?: array{a?: string}}', $request->validated());
}

class OptionalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tasks' => 'array',
            'tasks.*.name' => 'required|string',
            'info' => 'required|array',
            'info.status' => 'nullable|string',
            'a.b' => 'required|array',
            'a.b.c' => 'string',
            'groups' => 'required|array',
            'groups.*' => 'array',
            'groups.*.name' => 'string',
            'settings' => ['required', Rule::array()],
            'settings.theme' => 'string',
            'draft' => 'integer',
            'title' => 'exclude_if:draft,1|required|string',
            'editor' => 'exclude_if:draft,1|array',
            'editor.name' => 'required|string',
        ];
    }
}

class RequiredRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'author.name' => 'required|string',
            'tags' => 'required|array',
            'tags.*' => 'string',
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'meta' => 'required|array:a,b',
            'meta.a' => 'string',
            'rows' => 'required',
            'rows.*.id' => 'integer',
        ];
    }
}

class RuleObjectRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ex' => [Rule::excludeIf($this->boolean('flag')), 'required', 'string'],
            'exn' => [new ExcludeIf($this->boolean('flag')), 'required', 'string'],
            'r' => ['required', 'array', Rule::excludeIf($this->boolean('flag'))],
            'r.*' => 'string',
            'items' => ['required', new CustomRule()],
            'items.*.id' => 'integer',
            'opts' => ['required', Rule::array(['a', 'b'])],
            'opts.a' => 'string',
            'empty' => ['required', Rule::array([])],
            'empty.a' => 'string',
            'unknown' => ['required', new Stringy()],
            'unknown.a' => 'string',
        ];
    }
}

class CustomRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
    }
}

class Stringy implements Stringable
{
    public function __toString(): string
    {
        return 'array';
    }
}

class KeptRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'draft' => 'integer',
            'm' => 'required|array',
            'm.*.*' => 'integer',
            'a' => 'required|array',
            'a.*.b.*.c' => 'required',
            'info' => 'required|array',
            'info.title' => 'exclude_if:draft,1|required|string',
            'r' => 'required|array',
            'r.*' => 'exclude_if:draft,1|string',
            'p' => ['required', fn ($attribute, $value, $fail) => null],
            'p.*.id' => 'integer',
            'notes' => 'required|array',
            'notes.title' => 'exclude_if:draft,1|string',
        ];
    }
}

class PresenceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['present', 'nullable', 'string'],
            'terms' => 'accepted',
            'optout' => ['declined'],
            'maybe' => 'sometimes|present|string',
        ];
    }
}
