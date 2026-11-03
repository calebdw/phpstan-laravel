<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserFailure;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\SqlSchemaHelper;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use SplFileInfo;

use function array_key_exists;
use function explode;
use function file_get_contents;
use function ksort;

#[AutowiredService]
final class SchemaDumpParser
{
    /** @var array<string, SplFileInfo>|null */
    private array|null $files = null;

    /** @var list<string>|null */
    private array|null $directories = null;

    /** @param  string[] $schemaPaths */
    public function __construct(
        #[AutowiredParameter(ref: '%laravel.schemaDirectories%')]
        private array $schemaPaths,
        private FileHelper $fileHelper,
        private SqlSchemaHelper $sqlSchemaHelper,
        #[AutowiredParameter(ref: '%laravel.scanSchema%')]
        private bool $scanSchema,
        #[AutowiredParameter]
        string $currentWorkingDirectory = '',
    ) {
        if ($this->schemaPaths !== []) {
            return;
        }

        $this->schemaPaths = [$currentWorkingDirectory . '/database/schema'];
    }

    public function parseSchemaDumps(ModelSchema &$modelSchema): void
    {
        $files = $this->files();

        if ($files === []) {
            return;
        }

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
                $tables = $this->sqlSchemaHelper->tables($fileContents, $connectionName);
            } catch (SqlParserFailure $failure) {
                throw SqlParserFailure::unreadableDump($file->getPathname(), $failure);
            }

            foreach ($tables as $table) {
                if (array_key_exists($table->name, $connection->tables)) {
                    continue;
                }

                $connection->setTable($table);
            }

            $modelSchema->setConnection($connection);
        }
    }

    /** @return list<string> */
    public function directories(): array
    {
        if ($this->directories !== null) {
            return $this->directories;
        }

        return $this->directories = $this->scanSchema
            ? $this->fileHelper->getDirectories($this->schemaPaths)
            : [];
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
