<?php

declare(strict_types=1);

namespace FormRequest;

use App\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

use function PHPStan\Testing\assertType;

function test(FormRequest $request, AuthedRequest $authedRequest, StorePostRequest $post): void
{
    assertType('Illuminate\Support\ValidatedInput', $request->safe());
    assertType('array{key: mixed}', $request->safe(['key']));
    assertType('array<string, mixed>', $request->validated());

    // A narrowed user() override is respected; the base returns the union.
    assertType('App\User', $authedRequest->user());
    assertType('App\Admin|App\User|null', $request->user());

    assertType('array{title: string, body?: string|null, age?: int|numeric-string, status: \'draft\'|\'published\', kind: \'draft\'|\'published\', priority: 1|2|\'1\'|\'2\', role: \'admin\'|\'user\', size?: int<1, 10>|numeric-string, color?: \'blue\'|\'red\', tags?: list<string>, author: array{name: string}, avatar?: Illuminate\Http\UploadedFile}', $post->validated());
    assertType('string', $post->validated('title'));
    assertType('string', $post->title);
    assertType('string|null', $post->body);
    assertType('int|numeric-string', $post->age);
    assertType("'draft'|'published'", $post->status);
    assertType("'draft'|'published'", $post->kind);
    assertType("1|2|'1'|'2'", $post->priority);
    assertType("'admin'|'user'", $post->role);
    assertType('int<1, 10>|numeric-string', $post->size);
    assertType("'blue'|'red'", $post->color);
    assertType('list<string>', $post->tags);
    assertType('array{name: string}', $post->author);

    assertType('Illuminate\Support\ValidatedInput<array{title: string, body?: string|null, age?: int|numeric-string, status: \'draft\'|\'published\', kind: \'draft\'|\'published\', priority: 1|2|\'1\'|\'2\', role: \'admin\'|\'user\', size?: int<1, 10>|numeric-string, color?: \'blue\'|\'red\', tags?: list<string>, author: array{name: string}, avatar?: Illuminate\Http\UploadedFile}>', $post->safe());
    assertType('array{title: string, body?: string|null}', $post->safe(['title', 'body']));
    assertType('array{title: string, body?: string|null, age?: int|numeric-string, status: \'draft\'|\'published\', kind: \'draft\'|\'published\', priority: 1|2|\'1\'|\'2\', role: \'admin\'|\'user\', size?: int<1, 10>|numeric-string, color?: \'blue\'|\'red\', tags?: list<string>, author: array{name: string}, avatar?: Illuminate\Http\UploadedFile}', $post->safe()->all());
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

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

enum PostPriority: int
{
    case Low = 1;
    case High = 2;
}

class StorePostRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'body' => 'nullable|string',
            'age' => 'integer',
            'status' => ['required', Rule::enum(PostStatus::class)],
            'kind' => ['required', new Enum(PostStatus::class)],
            'priority' => ['required', Rule::enum(PostPriority::class)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'size' => 'integer|min:1|max:10',
            'color' => 'in:red,blue',
            'tags' => 'array',
            'tags.*' => 'string',
            'author.name' => 'required|string',
            'avatar' => 'image',
        ];
    }
}
