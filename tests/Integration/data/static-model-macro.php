<?php

declare(strict_types=1);

namespace Tests\Integration\Data;

use App\Post;

use function PHPStan\Testing\assertType;

assertType('string', Post::modelBoundMacro());
