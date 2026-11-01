<?php

namespace App;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryParent extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
}
