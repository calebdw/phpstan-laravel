<?php

declare(strict_types=1);

namespace Translate;

use function PHPStan\Testing\assertType;

function test(): void
{
    assertType('Illuminate\Contracts\Translation\Translator', trans());
    assertType('Illuminate\Contracts\Translation\Translator', trans(null));
    assertType('(array<mixed>|string)', trans('foo'));
    assertType('(array<mixed>|string)', trans('Hi :name', ['name' => 'Niek']));

    assertType('null', __());
    assertType('(array<mixed>|string)', __('foo'));
    assertType('(array<mixed>|string)', __('Hi :name', ['name' => 'Niek']));
}
