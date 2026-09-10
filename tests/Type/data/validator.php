<?php

declare(strict_types=1);

namespace ValidatorSafe;

use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

use function PHPStan\Testing\assertType;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

function test(Validator $validator): void
{
    assertType('Illuminate\Support\ValidatedInput<array<string, mixed>>', $validator->safe());
    assertType('array{key?: mixed}', $validator->safe(['key']));
    assertType('array<string, mixed>', $validator->validated());
    assertType('array<string, mixed>', $validator->validate());
}

function testFactory(Factory $factory, FactoryContract $contract): void
{
    $made = $factory->make([], [
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
    assertType('Illuminate\Validation\Validator<array{title: string, body?: string|null, age?: int|numeric-string, status: \'draft\'|\'published\', kind: \'draft\'|\'published\', role: \'admin\'|\'user\', size?: int<1, 10>|numeric-string, color?: \'blue\'|\'red\', tags?: list<string>, author: array{name: string}, avatar?: Illuminate\Http\UploadedFile}>', $made);
    assertType('array{title: string, body?: string|null, age?: int|numeric-string, status: \'draft\'|\'published\', kind: \'draft\'|\'published\', role: \'admin\'|\'user\', size?: int<1, 10>|numeric-string, color?: \'blue\'|\'red\', tags?: list<string>, author: array{name: string}, avatar?: Illuminate\Http\UploadedFile}', $made->validated());
    assertType('array{title: string, body?: string|null, age?: int|numeric-string, status: \'draft\'|\'published\', kind: \'draft\'|\'published\', role: \'admin\'|\'user\', size?: int<1, 10>|numeric-string, color?: \'blue\'|\'red\', tags?: list<string>, author: array{name: string}, avatar?: Illuminate\Http\UploadedFile}', $made->validate());
    assertType('array{title: string}', $factory->validate([], ['title' => 'required|string']));
    assertType('Illuminate\Support\ValidatedInput<array{title: string, body?: string|null, age?: int|numeric-string, status: \'draft\'|\'published\', kind: \'draft\'|\'published\', role: \'admin\'|\'user\', size?: int<1, 10>|numeric-string, color?: \'blue\'|\'red\', tags?: list<string>, author: array{name: string}, avatar?: Illuminate\Http\UploadedFile}>', $made->safe());
    assertType('array{title: string, body?: string|null}', $made->safe(['title', 'body']));

    $contractMade = $contract->make([], ['title' => 'required|string']);
    assertType('Illuminate\Contracts\Validation\Validator<array{title: string}>', $contractMade);
    assertType('array{title: string}', $contractMade->validated());
}

function testFacadeAndHelper(): void
{
    $fromFacade = ValidatorFacade::make([], ['title' => 'required|string', 'age' => 'integer']);
    assertType('Illuminate\Validation\Validator<array{title: string, age?: int|numeric-string}>', $fromFacade);
    assertType('array{title: string, age?: int|numeric-string}', $fromFacade->validated());
    assertType('array{title: string}', ValidatorFacade::validate([], ['title' => 'required|string']));

    $fromHelper = validator([], ['title' => 'required|string']);
    assertType('Illuminate\Validation\Validator<array{title: string}>', $fromHelper);
    assertType('array{title: string}', $fromHelper->validated());
    assertType('array{title: string}', validator([], ['title' => 'required|string'])->validate());
}
