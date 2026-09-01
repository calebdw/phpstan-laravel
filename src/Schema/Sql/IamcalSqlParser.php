<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema\Sql;

use iamcal\SQLParser as VendorSqlParser;
use iamcal\SQLParserSyntaxException;

use function array_key_exists;
use function is_array;
use function is_string;

final class IamcalSqlParser implements SqlParser
{
    /** @inheritDoc */
    public function parseTables(string $sql): array
    {
        $parser = new VendorSqlParser();
        // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
        $parser->throw_on_bad_syntax = true;

        try {
            $tableDefinitions = $parser->parse($sql);
        } catch (SQLParserSyntaxException $exception) {
            throw SqlParserFailure::create('Failed to parse SQL schema with iamcal/sql-parser.', $exception);
        }

        $tables = [];

        foreach ($tableDefinitions as $definition) {
            $tableName = $definition['name'] ?? null;

            if (! is_string($tableName)) {
                continue;
            }

            $fields = $definition['fields'] ?? null;

            if (! is_array($fields)) {
                continue;
            }

            $columns = [];

            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? null;
                $fieldType = $field['type'] ?? null;

                if (! is_string($fieldName) || ! is_string($fieldType)) {
                    continue;
                }

                $columns[] = new ColumnDefinition(
                    $fieldName,
                    $fieldType,
                    $this->resolveTypeOptions($field),
                    $this->resolveNullable($field),
                    $field['values'] ?? [],
                );
            }

            $tables[] = new TableDefinition($tableName, $columns);
        }

        return $tables;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return list<lowercase-string>
     */
    private function resolveTypeOptions(array $field): array
    {
        $result = [];

        if (array_key_exists('unsigned', $field) && $field['unsigned']) {
            $result[] = 'unsigned';
        }

        return $result;
    }

    /** @param array<string, mixed> $field */
    private function resolveNullable(array $field): bool
    {
        // The key is only present when the definition spelled out NULL or NOT
        // NULL, so its absence means neither was given and the column is
        // nullable per the SQL default. Assuming otherwise would report
        // `float DEFAULT 0` as non-nullable.
        if (array_key_exists('null', $field)) {
            return (bool) $field['null'];
        }

        return true;
    }
}
