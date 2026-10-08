<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Query\Builder;

class SecondCustomQueryBuilder extends Builder
{
    protected static $macros = [];

    public function customFilter(): static
    {
        return $this;
    }

    /** @return 22 */
    public function count($columns = '*'): int
    {
        return 22;
    }
}
