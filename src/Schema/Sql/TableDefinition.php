<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema\Sql;

/** A table as described by a schema dump, independent of the parser that read it. */
final class TableDefinition
{
    /** @param list<ColumnDefinition> $columns */
    public function __construct(
        public string $name,
        public array $columns,
    ) {
    }
}
