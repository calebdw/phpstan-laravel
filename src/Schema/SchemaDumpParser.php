<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserFailure;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\SqlSchemaHelper;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use SplFileInfo;

use function array_key_exists;
use function explode;
use function file_get_contents;
use function is_string;
use function ksort;
use function preg_match_all;
use function preg_quote;
use function preg_split;
use function str_replace;
use function trim;

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
        private ContainerHelper $containerHelper,
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

    /** @return array<string, array<string, true>> migrations already run, keyed by connection */
    public function parseSchemaDumps(ModelSchema &$modelSchema): array
    {
        $ranMigrations = [];

        foreach ($this->files() as $file) {
            $connectionName = $this->connectionName($file);
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

            $ranMigrations[$connectionName] = $this->ranMigrations($fileContents, $this->migrationTable($file));

            $modelSchema->setConnection($connection);
        }

        return $ranMigrations;
    }

    private function connectionName(SplFileInfo $file): string
    {
        // Laravel names dumps `connectionName-schema.{sql,dump}`.
        return explode('-schema', explode('.', $file->getBasename())[0])[0];
    }

    public function migrationTable(SplFileInfo $file): string
    {
        $config = $this->containerHelper->getConfigRepository();
        $table  = $config?->get('database.migrations.table') ?? $config?->get('database.migrations');
        $table  = is_string($table) ? $table : 'migrations';
        $prefix = $config?->get('database.connections.' . $this->connectionName($file) . '.prefix');
        $prefix = is_string($prefix) ? $prefix : '';
        $parts  = explode('.', $table, 2);

        return isset($parts[1]) ? $parts[0] . '.' . $prefix . $parts[1] : $prefix . $table;
    }

    /** @return array<string, true> */
    private function ranMigrations(string $sql, string $table): array
    {
        $parts   = explode('.', $table, 2);
        $pattern = isset($parts[1])
            ? $this->quotedIdentifier($parts[0]) . '\.' . $this->quotedIdentifier($parts[1])
            : '(?:(?:"(?:[^"\r\n]|"")+"|`(?:[^`\r\n]|``)+`|[\w$]+)\.)?' . $this->quotedIdentifier($parts[0]);

        // mysqldump and sqlite3 emit INSERTs; the migration name is the
        // only string value in Laravel's migration repository rows.
        preg_match_all('/^\h*INSERT\h+INTO\h+' . $pattern . '\h*(?:\([^\r\n]*?\)\h*)?VALUES\h*([^\r\n]*)/mi', $sql, $inserts);

        $names = [];

        foreach ($inserts[1] as $values) {
            preg_match_all("/'((?:[^']|'')+)'/", $values, $matches);

            foreach ($matches[1] as $name) {
                $names[str_replace("''", "'", $name)] = true;
            }
        }

        return $names + $this->copiedMigrations($sql, $pattern);
    }

    /** @return array<string, true> */
    private function copiedMigrations(string $sql, string $tablePattern): array
    {
        // pg_dump --data-only emits tab-delimited COPY data by default.
        preg_match_all(
            '/^\h*COPY\h+' . $tablePattern . '\h*\(([^)\r\n]*)\)\h+FROM\h+stdin;\R(.*?)^\\\\\.\h*(?=\R|$)/msi',
            $sql,
            $copies,
        );

        $names = [];

        foreach ($copies[1] as $i => $columns) {
            foreach (explode(',', $columns) as $column => $name) {
                if (trim($name, " \t\"") !== 'migration') {
                    continue;
                }

                foreach (preg_split('/\R/', $copies[2][$i]) ?: [] as $line) {
                    $name = explode("\t", $line)[$column] ?? '';

                    if ($name === '' || $name === '\N') {
                        continue;
                    }

                    $names[$name] = true;
                }

                break;
            }
        }

        return $names;
    }

    private function quotedIdentifier(string $identifier): string
    {
        return '(?:' . preg_quote($identifier, '/') . '|'
            . preg_quote('"' . str_replace('"', '""', $identifier) . '"', '/') . '|'
            . preg_quote('`' . str_replace('`', '``', $identifier) . '`', '/') . ')';
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
