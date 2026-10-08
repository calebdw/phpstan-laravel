<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Query\Builder;

class FirstCustomQueryBuilder extends Builder
{
    protected static $macros = [];

    public function customFilter(): static
    {
        return $this;
    }

    /** @return 11 */
    public function count($columns = '*'): int
    {
        return 11;
    }

    public function exists(): bool
    {
        return true;
    }
}
