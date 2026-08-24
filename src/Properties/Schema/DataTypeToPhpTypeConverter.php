<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties\Schema;

interface DataTypeToPhpTypeConverter
{
    /**
     * Describe a column's SQL type as a PHP type.
     *
     * @param list<lowercase-string> $options
     * @param list<string>           $values
     */
    public function convert(string $type, array $options = [], array $values = []): string;
}
