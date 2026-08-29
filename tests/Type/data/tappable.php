<?php

namespace Tappable;

use Illuminate\Support\Traits\Tappable;

use function PHPStan\Testing\assertType;
use function tap;

class TappableClass
{
    use Tappable;

    public function touch(): int
    {
        return 1;
    }
}

class OtherTappableClass
{
    public function touch(): string
    {
        return '';
    }
}

function test(TappableClass|OtherTappableClass $tappable): void
{
    assertType(
        'Tappable\TappableClass',
        (new TappableClass())->tap(function (TappableClass $tappable) {
        }),
    );

    assertType(
        'Illuminate\Support\HigherOrderTapProxy<Tappable\TappableClass>',
        (new TappableClass())->tap(),
    );

    assertType(
        'Tappable\OtherTappableClass|Tappable\TappableClass',
        tap($tappable)->touch(),
    );
}
