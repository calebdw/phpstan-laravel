<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema\Sql;

/** A column as described by a schema dump, independent of the parser that read it. */
final class ColumnDefinition
{
    /**
     * @param list<lowercase-string> $typeOptions
     * @param list<string>           $values
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $typeOptions,
        public bool $nullable,
        public array $values = [],
        public SqlDialect $dialect = SqlDialect::MySql,
    ) {
    }
}
