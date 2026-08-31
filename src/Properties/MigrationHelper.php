<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties;

use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\ModelHelper;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Reflection\ReflectionProvider;
use SplFileInfo;

use function count;
use function database_path;
use function uasort;

class MigrationHelper
{
    public function __construct(
        private Parser $parser,
        /** @var string[] */
        private array $databaseMigrationPath,
        private FileHelper $fileHelper,
        private bool $scanMigrations,
        private ModelHelper $modelHelper,
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function parseMigrations(ModelDatabaseHelper &$modelDatabaseHelper): void
    {
        if (! $this->scanMigrations) {
            return;
        }

        if (count($this->databaseMigrationPath) === 0) {
            $this->databaseMigrationPath = [database_path('migrations')];
        }

        $schemaAggregator = new SchemaAggregator($modelDatabaseHelper, $this->modelHelper, $this->reflectionProvider);
        $filesArray       = $this->fileHelper->getFiles($this->databaseMigrationPath, '/\.php$/i', recursive: false);

        if (empty($filesArray)) {
            return;
        }

        uasort($filesArray, static function (SplFileInfo $a, SplFileInfo $b) {
            return $a->getFilename() <=> $b->getFilename();
        });

        foreach ($filesArray as $file) {
            try {
                $schemaAggregator->addStatements($this->parser->parseFile($file->getPathname()));
            } catch (ParserErrorsException) {
                continue;
            }
        }
    }
}
