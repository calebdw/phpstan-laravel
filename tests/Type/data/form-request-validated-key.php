<?php

declare(strict_types=1);

namespace FormRequestValidatedKey;

use Illuminate\Foundation\Http\FormRequest;

use function PHPStan\Testing\assertType;

function test(StorePost $post, StorePost|StoreComment $either, StoreTree $tree, string|null $key): void
{
    assertType('string', $post->validated('title'));
    assertType('string', $post->validated('author.name'));
    assertType('string|null', $post->validated('nick'));
    assertType('5|string', $post->validated('nick', 5));
    assertType('5', $post->validated('nope', 5));
    assertType('list<string>|null', $post->validated('tags.*'));
    assertType('array{title: string, nick?: string, author: array{name: string}, tags?: array<int|string, string>}', $post->validated());
    assertType('array{title: string, nick?: string, author: array{name: string}, tags?: array<int|string, string>}', $post->validated(null));

    assertType('string|null', $either->validated('title'));
    assertType("'none'|int|numeric-string", $either->validated('count', 'none'));

    assertType('list<string>|null', $tree->validated('nested.*.tags.*'));
    assertType('list<int|numeric-string>|null', $tree->validated('ids.*'));
    assertType('int|numeric-string|null', $tree->validated('ids.0'));
    assertType('mixed', $post->validated($key));
    assertType("'time'", $post->validated('nope', 'time'));
}

class StorePost extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'nick' => ['string'],
            'author.name' => 'required|string',
            'tags' => 'array',
            'tags.*' => 'string',
        ];
    }
}

class StoreComment extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'count' => 'required|integer',
        ];
    }
}

class StoreTree extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nested' => 'array',
            'nested.*.tags' => 'array',
            'nested.*.tags.*' => 'string',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
        ];
    }
}
