<?php

declare(strict_types=1);

namespace App\BareRelations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }
}
