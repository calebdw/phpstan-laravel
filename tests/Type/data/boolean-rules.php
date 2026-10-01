<?php

declare(strict_types=1);

namespace BooleanRules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

use function PHPStan\Testing\assertType;

function testValidator(): void
{
    $validated = Validator::make([], [
        'flag' => 'boolean',
        'alias' => ['bool'],
        'strict' => 'boolean:strict',
        'strict_alias' => ['required', 'bool:strict'],
        'maybe' => 'nullable|boolean',
        'terms' => 'accepted',
        'optout' => ['declined'],
        'both' => 'boolean|accepted',
        'picked' => 'boolean|in:0,1',
    ])->validated();

    assertType("0|1|'0'|'1'|bool", $validated['flag']);
    assertType("0|1|'0'|'1'|bool", $validated['alias']);
    assertType('bool', $validated['strict']);
    assertType('bool', $validated['strict_alias']);
    assertType("0|1|'0'|'1'|bool|null", $validated['maybe']);
    assertType("1|'1'|'on'|'true'|'yes'|true", $validated['terms']);
    assertType("0|'0'|'false'|'no'|'off'|false", $validated['optout']);
    assertType("1|'1'|true", $validated['both']);
    assertType("'0'|'1'", $validated['picked']);
}

function testFormRequest(BooleanRequest $request): void
{
    assertType("0|1|'0'|'1'|bool", $request->flag);
    assertType('bool', $request->strict);
    assertType("1|'1'|'on'|'true'|'yes'|true", $request->terms);
    assertType("0|'0'|'false'|'no'|'off'|false", $request->optout);
    assertType("1|'1'|true", $request->both);
    assertType("'0'|'1'", $request->picked);
}

class BooleanRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'flag' => 'boolean',
            'strict' => ['bool:strict'],
            'terms' => 'accepted',
            'optout' => ['declined'],
            'both' => 'boolean|accepted',
            'picked' => 'boolean|in:0,1',
        ];
    }
}
