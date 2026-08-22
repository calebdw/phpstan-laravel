<?php

namespace Passthru;

use App\User;

use function PHPStan\Testing\assertType;

function test() {
    assertType('int<0, max>', User::query()->getCountForPagination());
}
