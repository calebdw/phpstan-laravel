<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\ModelHelper;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Reflection\ReflectionProvider;
use SplFileInfo;

use function uasort;

class MigrationFileParser
{
    /** @var array<string, SplFileInfo>|null */
    private array|null $files = null;

    public function __construct(
        private Parser $parser,
        /** @var string[] */
        private array $databaseMigrationPath,
        private FileHelper $fileHelper,
        private bool $scanMigrations,
        private ModelHelper $modelHelper,
        private ReflectionProvider $reflectionProvider,
        string $currentWorkingDirectory = '',
    ) {
        if ($this->databaseMigrationPath !== []) {
            return;
        }

        $this->databaseMigrationPath = [$currentWorkingDirectory . '/database/migrations'];
    }

    public function parseMigrations(ModelSchema &$modelSchema): void
    {
        $schemaParser = new MigrationSchemaParser($modelSchema, $this->modelHelper, $this->reflectionProvider);

        foreach ($this->files() as $file) {
            try {
                $schemaParser->addStatements($this->parser->parseFile($file->getPathname()));
            } catch (ParserErrorsException) {
                continue;
            }
        }
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
