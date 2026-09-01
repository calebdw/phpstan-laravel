<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use CalebDW\PhpstanLaravel\Schema\Type\DataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Schema\Type\PostgresDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Schema\Type\SqlDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlDialect;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserFailure;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use SplFileInfo;

use function array_key_exists;
use function explode;
use function file_get_contents;
use function ksort;

final class SchemaDumpParser
{
    /** @var array<string, SplFileInfo>|null */
    private array|null $files = null;

    /** @param  string[] $schemaPaths */
    public function __construct(
        private array $schemaPaths,
        private FileHelper $fileHelper,
        private SqlDataTypeToPhpTypeConverter $converter,
        private PostgresDataTypeToPhpTypeConverter $postgresConverter,
        private SqlParserManager $parserManager,
        private bool $scanSchema,
        string $currentWorkingDirectory = '',
    ) {
        if ($this->schemaPaths !== []) {
            return;
        }

        $this->schemaPaths = [$currentWorkingDirectory . '/database/schema'];
    }

    private function converterFor(SqlDialect $dialect): DataTypeToPhpTypeConverter
    {
        return match ($dialect) {
            SqlDialect::MySql => $this->converter,
            SqlDialect::Postgres => $this->postgresConverter,
        };
    }

    public function parseSchemaDumps(ModelSchema &$modelSchema): void
    {
        $files = $this->files();

        if ($files === []) {
            return;
        }

        // Resolved lazily: neither parser is a hard requirement, so a project
        // without schema dumps never needs one installed.
        $parser = $this->parserManager->driver();

        foreach ($files as $file) {
            // Laravel generates schema files with the format `connectionName-schema.{sql,dump}`
            // If the file name does not match the expected format, then we just use the
            // file name as the connection name.
            $baseName       = explode('.', $file->getBasename())[0];
            $connectionName = explode('-schema', $baseName)[0];
            $connection     = new Connection($connectionName);

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

                $table = new Table($definition->name);

                foreach ($definition->columns as $column) {
                    $table->setColumn(new Column(
                        $column->name,
                        $this->converterFor($column->dialect)
                            ->convert($column->type, $column->typeOptions, $column->values),
                        $column->nullable,
                    ));
                }

                $connection->setTable($table);
            }

            $modelSchema->setConnection($connection);
        }
    }

    /** @return array<string, SplFileInfo> */
    public function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        if (! $this->scanSchema) {
            return $this->files = [];
        }

        $this->files = $this->fileHelper->getFiles($this->schemaPaths, '/\.dump|\.sql/i');
        ksort($this->files);

        return $this->files;
    }
}
