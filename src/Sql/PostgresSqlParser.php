<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Sql;

use CalebDW\PgSchemaParser\Column;
use CalebDW\PgSchemaParser\Database;
use CalebDW\PgSchemaParser\PgDumpParser;
use CalebDW\PgSchemaParser\Table;
use CalebDW\PhpstanLaravel\Properties\Schema\PostgresDataTypeToPhpTypeConverter;
use Throwable;

final class PostgresSqlParser implements SqlParser
{
    private const string DEFAULT_SCHEMA = 'public';

    public function __construct(
        private readonly PgDumpParser $parser = new PgDumpParser(),
    ) {
    }

    /** @inheritDoc */
    public function parseTables(string $sql): array
    {
        try {
            $database = $this->parser->parse($sql);
        } catch (Throwable $exception) {
            throw SqlParserFailure::create('Failed to parse SQL schema with calebdw/pg-schema-parser.', $exception);
        }

        $tables = [];

        foreach ($database->tables as $table) {
            $columns = [];

            foreach ($table->columns as $column) {
                $columns[] = $this->convertColumn($database, $column);
            }

            $tables[] = new TableDefinition($this->tableName($table), $columns);
        }

        return $tables;
    }

    private function tableName(Table $table): string
    {
        return $table->schema === self::DEFAULT_SCHEMA
            ? $table->name
            : $table->qualifiedName();
    }

    private function convertColumn(Database $database, Column $column): ColumnDefinition
    {
        $type = $database->resolve($column->type);
        $enum = $database->enumFor($type);

        $options = $type->isArray() || $column->type->isArray()
            ? [PostgresDataTypeToPhpTypeConverter::OPTION_ARRAY]
            : [];

        return new ColumnDefinition(
            $column->name,
            $enum === null ? $type->name : PostgresDataTypeToPhpTypeConverter::TYPE_ENUM,
            $options,
            $column->nullable,
            $enum === null ? [] : $enum->values,
            SqlDialect::Postgres,
        );
    }
}
