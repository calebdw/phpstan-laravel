<?php

declare(strict_types=1);

namespace RequestValidate;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

use function PHPStan\Testing\assertType;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

function test(Request $request): void
{
    $data = $request->validate([
        'title' => ['required', 'string'],
        'body' => 'nullable|string',
        'age' => 'integer',
        'status' => ['required', Rule::enum(PostStatus::class)],
        'kind' => ['required', new Enum(PostStatus::class)],
        'role' => ['required', Rule::in(['admin', 'user'])],
        'size' => 'integer|min:1|max:10',
        'color' => 'in:red,blue',
        'tags' => 'array',
        'tags.*' => 'string',
        'author.name' => 'required|string',
        'avatar' => 'image',
    ]);

    assertType('array{title: string, body?: string|null, age?: int|numeric-string, status: \'draft\'|\'published\', kind: \'draft\'|\'published\', role: \'admin\'|\'user\', size?: int<1, 10>|numeric-string, color?: \'blue\'|\'red\', tags?: list<string>, author: array{name: string}, avatar?: Illuminate\Http\UploadedFile}', $data);
    assertType('string', $request->title);
    assertType('string|null', $request->body);
    assertType('int|numeric-string', $request->age);
    assertType("'draft'|'published'", $request->status);
    assertType("'admin'|'user'", $request->role);
    assertType('array{name: string}', $request->author);
    assertType('App\Admin|App\User|null', $request->user());
    assertType('Symfony\Component\HttpFoundation\HeaderBag', $request->headers);
}

function testValidateWithBag(Request $request): void
{
    $data = $request->validateWithBag('post', [
        'title' => ['required', 'string'],
        'age' => 'integer',
    ]);

    assertType('array{title: string, age?: int|numeric-string}', $data);
    assertType('string', $request->title);
}

function testStatement(Request $request): void
{
    $request->validate([
        'title' => ['required', 'string'],
    ]);

    assertType('string', $request->title);
}
