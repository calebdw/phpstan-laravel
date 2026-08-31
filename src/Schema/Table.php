<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

/** @see https://github.com/psalm/laravel-psalm-plugin/blob/master/src/SchemaTable.php */
final class Table
{
    /** @var array<string, Column> */
    public array $columns = [];

    public function __construct(public string $name)
    {
    }

    public function setColumn(Column $column): void
    {
        $this->columns[$column->name] = $column;
    }

    public function renameColumn(string $oldName, string $newName): void
    {
        if (! isset($this->columns[$oldName])) {
            return;
        }

        $oldColumn = $this->columns[$oldName];

        unset($this->columns[$oldName]);

        $oldColumn->name = $newName;

        $this->columns[$newName] = $oldColumn;
    }

    public function dropColumn(string $columnName): void
    {
        unset($this->columns[$columnName]);
    }
}
