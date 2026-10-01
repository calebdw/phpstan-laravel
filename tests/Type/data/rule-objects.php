<?php

declare(strict_types=1);

namespace RuleObjects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\ImageFile;

use function PHPStan\Testing\assertType;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}

function testValidator(bool $flag): void
{
    $validated = Validator::make([], [
        'rule_file' => ['required', Rule::file()],
        'rule_image' => ['required', Rule::imageFile()],
        'rule_dimensions' => ['required', Rule::dimensions(['min_width' => 1])],
        'types' => ['required', File::types(['pdf', 'docx'])->max('25mb')],
        'image' => ['required', File::image()],
        'default' => ['required', File::default()],
        'defaults' => ['required', File::defaults()],
        'new_file' => ['required', new File()],
        'new_image' => ['required', new ImageFile()],
        'new_dimensions' => ['required', new Dimensions([])],
        'extensions' => 'required|extensions:pdf',
        'dimensions' => 'required|dimensions:min_width=1',
        'status' => ['required', Rule::enum(Status::class)->only([Status::Draft])],
        'exists' => ['required', Rule::exists('users', 'id')],
        'either' => ['required', $flag ? File::image() : 'string'],
    ])->validated();

    assertType('Illuminate\Http\UploadedFile', $validated['rule_file']);
    assertType('Illuminate\Http\UploadedFile', $validated['rule_image']);
    assertType('Illuminate\Http\UploadedFile', $validated['rule_dimensions']);
    assertType('Illuminate\Http\UploadedFile', $validated['types']);
    assertType('Illuminate\Http\UploadedFile', $validated['image']);
    assertType('Illuminate\Http\UploadedFile', $validated['default']);
    assertType('Illuminate\Http\UploadedFile', $validated['defaults']);
    assertType('Illuminate\Http\UploadedFile', $validated['new_file']);
    assertType('Illuminate\Http\UploadedFile', $validated['new_image']);
    assertType('Illuminate\Http\UploadedFile', $validated['new_dimensions']);
    assertType('Illuminate\Http\UploadedFile', $validated['extensions']);
    assertType('Illuminate\Http\UploadedFile', $validated['dimensions']);
    assertType("'draft'|'published'", $validated['status']);
    assertType('string', $validated['exists']);
    assertType('Illuminate\Http\UploadedFile|string', $validated['either']);

    $nested = Validator::make([], [
        'maybe' => [$flag ? 'required' : 'nullable', 'string'],
        'deep' => ['required', $flag ? 'integer' : ($flag ? 'boolean:strict' : 'numeric')],
    ]);
    assertType('array{maybe?: string|null, deep: bool|float|int|numeric-string}', $nested->validated());
}

function testFormRequest(UploadRequest $request): void
{
    assertType('Illuminate\Http\UploadedFile', $request->rule_file);
    assertType('Illuminate\Http\UploadedFile', $request->rule_image);
    assertType('Illuminate\Http\UploadedFile', $request->rule_dimensions);
    assertType('Illuminate\Http\UploadedFile', $request->types);
    assertType('Illuminate\Http\UploadedFile', $request->image);
    assertType('Illuminate\Http\UploadedFile', $request->default);
    assertType('Illuminate\Http\UploadedFile', $request->defaults);
    assertType('Illuminate\Http\UploadedFile', $request->new_file);
    assertType('Illuminate\Http\UploadedFile', $request->new_image);
    assertType('Illuminate\Http\UploadedFile', $request->new_dimensions);
    assertType('Illuminate\Http\UploadedFile', $request->extensions);
    assertType('Illuminate\Http\UploadedFile', $request->dimensions);
    assertType("'draft'|'published'", $request->status);
    assertType('string', $request->exists);
    assertType('Illuminate\Http\UploadedFile|string', $request->either);
}

class UploadRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rule_file' => ['required', Rule::file()],
            'rule_image' => ['required', Rule::imageFile()],
            'rule_dimensions' => ['required', Rule::dimensions(['min_width' => 1])],
            'types' => ['required', File::types(['pdf', 'docx'])->max('25mb')],
            'image' => ['required', File::image()],
            'default' => ['required', File::default()],
            'defaults' => ['required', File::defaults()],
            'new_file' => ['required', new File()],
            'new_image' => ['required', new ImageFile()],
            'new_dimensions' => ['required', new Dimensions([])],
            'extensions' => 'required|extensions:pdf',
            'dimensions' => 'required|dimensions:min_width=1',
            'status' => ['required', Rule::enum(Status::class)->only([Status::Draft])],
            'exists' => ['required', Rule::exists('users', 'id')],
            'either' => ['required', $this->boolean('flag') ? File::image() : 'string'],
        ];
    }
}
