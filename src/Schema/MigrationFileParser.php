<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\ModelHelper;
use CalebDW\PhpstanLaravel\Support\SqlSchemaHelper;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Reflection\ReflectionProvider;
use SplFileInfo;

use function array_keys;
use function str_replace;
use function uasort;

#[AutowiredService]
final class MigrationFileParser
{
    /** @var array<string, SplFileInfo>|null */
    private array|null $files = null;

    /** @var list<string>|null */
    private array|null $directories = null;

    public function __construct(
        #[AutowiredParameter(ref: '@currentPhpVersionSimpleDirectParser')]
        private Parser $parser,
        /** @var string[] */
        #[AutowiredParameter(ref: '%laravel.migrationDirectories%')]
        private array $databaseMigrationPath,
        private FileHelper $fileHelper,
        #[AutowiredParameter(ref: '%laravel.scanMigrations%')]
        private bool $scanMigrations,
        private ModelHelper $modelHelper,
        private ReflectionProvider $reflectionProvider,
        private InitializerExprTypeResolver $initializerExprTypeResolver,
        private SqlSchemaHelper $sqlSchemaHelper,
        #[AutowiredParameter]
        string $currentWorkingDirectory = '',
    ) {
        if ($this->databaseMigrationPath !== []) {
            return;
        }

        $this->databaseMigrationPath = [$currentWorkingDirectory . '/database/migrations'];
    }

    /** @param array<string, array<string, true>> $ranMigrations */
    public function parseMigrations(ModelSchema &$modelSchema, array $ranMigrations = []): void
    {
        $schemaParser = new MigrationSchemaParser(
            $modelSchema,
            $this->modelHelper,
            $this->reflectionProvider,
            $this->initializerExprTypeResolver,
            $this->sqlSchemaHelper,
        );

        $connectionsByMigration = [];

        foreach ($ranMigrations as $connection => $migrations) {
            foreach (array_keys($migrations) as $migration) {
                $connectionsByMigration[$migration][$connection] = true;
            }
        }

        foreach ($this->files() as $file) {
            // Match Migrator::getMigrationName(), which strips every `.php`.
            $migration = str_replace('.php', '', $file->getFilename());

            try {
                $schemaParser->addStatements(
                    $this->parser->parseFile($file->getPathname()),
                    $connectionsByMigration[$migration] ?? [],
                );
            } catch (ParserErrorsException) {
                continue;
            }
        }
    }

    /** @return list<string> */
    public function directories(): array
    {
        if ($this->directories !== null) {
            return $this->directories;
        }

        return $this->directories = $this->scanMigrations
            ? $this->fileHelper->getDirectories($this->databaseMigrationPath)
            : [];
    }

    /** @return array<string, SplFileInfo> */
    public function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        if (! $this->scanMigrations) {
            return $this->files = [];
        }

        $this->files = $this->fileHelper->getFiles($this->databaseMigrationPath, '/\.php$/i', recursive: false);

        uasort($this->files, static fn ($a, $b) => $a->getFilename() <=> $b->getFilename());

        return $this->files;
    }
}
