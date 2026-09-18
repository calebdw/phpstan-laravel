<?php

declare(strict_types=1);

namespace App\BareRelations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Owner extends Model
{
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
