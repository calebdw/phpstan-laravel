<?php

namespace EagerLoadClosures;

use App\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

function test(): void
{
    User::query()->with(['accounts' => fn ($q) => $q->active()]);
    User::query()->with(['accounts' => fn (HasMany $q) => $q]);
}
