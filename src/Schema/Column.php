<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

/** @see https://github.com/psalm/laravel-psalm-plugin/blob/master/src/SchemaColumn.php */
final class Column
{
    public string $writeableType;

    public function __construct(
        public string $name,
        public string $readableType,
        public bool $nullable = false,
        /** @var array<int, string> */
        public array|null $options = null,
        string|null $writeableType = null,
    ) {
        $this->writeableType = $writeableType ?? $this->readableType;
    }

    /** @param array{name: string, readableType: string, nullable: bool, options: array<int, string>|null, writeableType: string} $properties */
    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['name'],
            $properties['readableType'],
            $properties['nullable'],
            $properties['options'],
            $properties['writeableType'],
        );
    }
}
