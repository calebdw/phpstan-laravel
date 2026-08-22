<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Sql;

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Exceptions\ParserException;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;

use function array_values;
use function is_array;
use function strtolower;
use function trim;

final class PhpMyAdminSqlParser implements SqlParser
{
    /** @inheritDoc */
    public function parseTables(string $sql): array
    {
        try {
            $parser = new Parser($sql, true);
        } catch (ParserException $exception) {
            throw SqlParserFailure::create('Failed to parse SQL schema with phpmyadmin/sql-parser.', $exception);
        }

        $tables = [];

        foreach ($parser->statements as $statement) {
            if (! $statement instanceof CreateStatement || $statement->name?->table === null) {
                continue;
            }

            if (! is_array($statement->fields)) {
                continue;
            }

            $columns = [];

            foreach ($statement->fields as $field) {
                if ($field->name === null || $field->type === null) {
                    continue;
                }

                $columns[] = new ColumnDefinition(
                    $field->name,
                    $field->type->name,
                    $this->convertTypeOptions($field->type->options),
                    $this->isNullable($field),
                    $this->resolveValues($field),
                );
            }

            // Keyed by name so that a table created more than once collapses to
            // its last definition, as required by SqlParser::parseTables().
            $tables[$statement->name->table] = new TableDefinition($statement->name->table, $columns);
        }

        return array_values($tables);
    }

    private function isNullable(CreateDefinition $definition): bool
    {
        return ! $definition->options?->has('NOT NULL');
    }

    /** @return list<string> */
    private function resolveValues(CreateDefinition $definition): array
    {
        $values = $definition->type->parameters ?? [];

        $result = [];

        foreach ($values as $value) {
            $result[] = trim((string) $value, "'\"");
        }

        return $result;
    }

    /** @return list<lowercase-string> */
    private function convertTypeOptions(OptionsArray $options): array
    {
        $result = [];

        foreach ($options->options as $option) {
            if (is_array($option)) {
                $result[] = strtolower($option['name']);
            } else {
                $result[] = strtolower($option);
            }
        }

        return $result;
    }
}
