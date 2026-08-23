<?php

declare(strict_types=1);

namespace Tests\Rules\Data;

use Illuminate\Database\Eloquent\Model;

class ModelAppendsLegacyAccessor extends Model
{
    /** @var array<int, string> */
    protected $appends = [
        'full_name',
    ];

    /**
     * A public method sharing the accessor's camel case name. This used to
     * stop the legacy accessor below from ever being looked for.
     */
    public function fullName(): string
    {
        return $this->getFullNameAttribute();
    }

    public function getFullNameAttribute(): string
    {
        return 'Taylor Otwell';
    }
}
