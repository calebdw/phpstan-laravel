<?php

declare(strict_types=1);

namespace Tests\Integration\Data;

use App\Post;
use Illuminate\Support\Str;

Str::plainClosureMacro();
Post::modelBoundMacro();
