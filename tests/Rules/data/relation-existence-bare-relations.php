<?php

declare(strict_types=1);

namespace RelationExistenceBareRelations;

use App\BareRelations\Owner;
use App\User;

Owner::query()->whereHas('items.category', function ($q) {
    $q->whereHas('labels', fn ($q) => $q->whereKey(1));
});

Owner::query()->whereHas('items', fn ($q) => $q->whereHas('category'));
User::query()->whereHas('posts.comments', fn ($q) => $q->whereHas('missing'));
