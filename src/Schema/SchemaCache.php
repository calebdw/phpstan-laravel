<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use Composer\InstalledVersions;
use Throwable;

use function dirname;
use function file_put_contents;
use function getmypid;
use function hash_final;
use function hash_init;
use function hash_update;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function ksort;
use function mkdir;
use function rename;
use function sprintf;
use function unlink;
use function var_export;

use const LOCK_EX;
use const PHP_VERSION;

final class SchemaCache
{
    public function __construct(
        private MigrationFileParser $migrationFileParser,
        private SchemaDumpParser $schemaDumpParser,
        private string $tmpDir,
    ) {
    }

    public function inputHash(): string
    {
        $context = hash_init('xxh128');
        hash_update($context, $this->compatibilityKey() . "\0");

        $files = [];

        foreach ($this->migrationFileParser->files() as $file) {
            $files['migration:' . $file->getPathname()] = $file;
        }

        foreach ($this->schemaDumpParser->files() as $file) {
            $files['schema:' . $file->getPathname()] = $file;
        }

        ksort($files);

        foreach ($files as $identity => $file) {
            hash_update($context, sprintf(
                "%s\0%d\0%d\0",
                $identity,
                $file->getMTime(),
                $file->getSize(),
            ));
        }

        return hash_final($context);
    }

    /** @return array<string, Connection>|null */
    public function load(string $inputHash): array|null
    {
        $path = $this->path($inputHash);

        if (! is_file($path)) {
            return null;
        }

        try {
            $data = require $path;
        } catch (Throwable) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        foreach ($data as $connectionName => $connection) {
            if (! is_string($connectionName) || ! $connection instanceof Connection) {
                return null;
            }
        }

        return $data;
    }

    /** @param array<string, Connection> $connections */
    public function save(string $inputHash, array $connections): void
    {
        $path      = $this->path($inputHash);
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            return;
        }

        $temporaryPath = $path . '.tmp.' . getmypid();
        $contents      = "<?php\n\nreturn " . var_export($this->sort($connections), true) . ";\n";

        if (@file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            return;
        }

        if (@rename($temporaryPath, $path)) {
            return;
        }

        @unlink($temporaryPath);
    }

    /**
     * @param  array<string, Connection> $connections
     *
     * @return array<string, Connection>
     */
    private function sort(array $connections): array
    {
        ksort($connections);

        foreach ($connections as $connection) {
            ksort($connection->tables);

            foreach ($connection->tables as $table) {
                ksort($table->columns);
            }
        }

        return $connections;
    }

    private function compatibilityKey(): string
    {
        return sprintf(
            "php:%s\0phpstan-laravel:%s@%s\0phpstan:%s@%s",
            PHP_VERSION,
            InstalledVersions::getPrettyVersion('calebdw/phpstan-laravel') ?? 'unknown',
            InstalledVersions::getReference('calebdw/phpstan-laravel') ?? 'unknown',
            InstalledVersions::getPrettyVersion('phpstan/phpstan') ?? 'unknown',
            InstalledVersions::getReference('phpstan/phpstan') ?? 'unknown',
        );
    }

    private function path(string $inputHash): string
    {
        return $this->tmpDir . '/phpstan-laravel/schema-' . $inputHash . '.php';
    }
}
