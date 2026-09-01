<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

final class Connection
{
    public function __construct(
        public string $name,
        /** @var array<string, Table> */
        public array $tables = [],
    ) {
    }

    public function setTable(Table $table): void
    {
        $this->tables[$table->name] = $table;
    }

    public function renameTable(string $oldName, string $newName): void
    {
        if (! isset($this->tables[$oldName])) {
            return;
        }

        $oldTable = $this->tables[$oldName];

        unset($this->tables[$oldName]);

        $oldTable->name = $newName;

        $this->tables[$newName] = $oldTable;
    }

    public function dropTable(string $tableName): void
    {
        unset($this->tables[$tableName]);
    }

    /** @param array{name: string, tables: array<string, Table>} $properties */
    public static function __set_state(array $properties): self
    {
        return new self($properties['name'], $properties['tables']);
    }
}
