<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class PostComment extends Comment
{
    /** @return MorphTo<Post, $this> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
