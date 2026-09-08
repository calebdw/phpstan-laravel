<?php

declare(strict_types=1);

namespace Tests\Rules\Queue\Data;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;

class ProductFix extends Model
{
}

class JobMissingSerializesModelsFix implements ShouldQueue
{
    public function __construct(public ProductFix $product)
    {
    }
}
