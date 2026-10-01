<?php

namespace ModelPropertyCastWrites;

use App\Address;
use App\User;

function writes(User $user, Address $address): void
{
    $user->int = 'abc';
    $user->blocked = 'yes';
    $address->custom_foreign_id_for_name = '5';
}
