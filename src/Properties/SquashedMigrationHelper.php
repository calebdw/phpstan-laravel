<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties;

use CalebDW\PhpstanLaravel\Properties\Schema\DataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Properties\Schema\PostgresDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Properties\Schema\SqlDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Sql\SqlDialect;
use CalebDW\PhpstanLaravel\Sql\SqlParserFailure;
use CalebDW\PhpstanLaravel\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Support\FileHelper;

use function array_key_exists;
use function database_path;
use function explode;
use function file_get_contents;
use function ksort;

final class SquashedMigrationHelper
{
    /** @param  string[] $schemaPaths */
    public function __construct(
        private array $schemaPaths,
        private FileHelper $fileHelper,
        private SqlDataTypeToPhpTypeConverter $converter,
        private PostgresDataTypeToPhpTypeConverter $postgresConverter,
        private SqlParserManager $parserManager,
        private bool $scanSchema,
    ) {
    }

    private function converterFor(SqlDialect $dialect): DataTypeToPhpTypeConverter
    {
        return match ($dialect) {
            SqlDialect::MySql => $this->converter,
            SqlDialect::Postgres => $this->postgresConverter,
        };
    }

    public function parseSchemaDumps(ModelDatabaseHelper &$modelDatabaseHelper): void
    {
        if (! $this->scanSchema) {
            return;
        }

        if (empty($this->schemaPaths)) {
            $this->schemaPaths = [database_path('schema')];
        }

        $filesArray = $this->fileHelper->getFiles($this->schemaPaths, '/\.dump|\.sql/i');

        if (empty($filesArray)) {
            return;
        }

        ksort($filesArray);

        // Resolved lazily: neither parser is a hard requirement, so a project
        // without schema dumps never needs one installed.
        $parser = $this->parserManager->driver();

        foreach ($filesArray as $file) {
            // Laravel generates schema files with the format `connectionName-schema.{sql,dump}`
            // If the file name does not match the expected format, then we just use the
            // file name as the connection name.
            $baseName       = explode('.', $file->getBasename())[0];
            $connectionName = explode('-schema', $baseName)[0];
            $connection     = new SchemaConnection($connectionName);

            $fileContents = file_get_contents($file->getPathname());

            if ($fileContents === false) {
                continue;
            }

            try {
                $tableDefinitions = $parser->parseTables($fileContents);
            } catch (SqlParserFailure $failure) {
                throw SqlParserFailure::unreadableDump($file->getPathname(), $failure);
            }

            foreach ($tableDefinitions as $definition) {
                if (array_key_exists($definition->name, $connection->tables)) {
                    continue;
                }

                $table = new SchemaTable($definition->name);

                foreach ($definition->columns as $column) {
                    $table->setColumn(new SchemaColumn(
                        $column->name,
                        $this->converterFor($column->dialect)
                            ->convert($column->type, $column->typeOptions, $column->values),
                        $column->nullable,
                    ));
                }

                $connection->setTable($table);
            }

            $modelDatabaseHelper->setConnection($connection);
        }
    }
}
