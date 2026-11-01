<?php

namespace PaginationSignatures;

use App\User;

User::query()->paginate(fn (string $total) => 15);
User::query()->paginate(fn (int $total) => '15');
