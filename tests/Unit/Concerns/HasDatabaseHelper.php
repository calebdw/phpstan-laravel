<?php

declare(strict_types=1);

namespace Tests\Unit\Concerns;

use CalebDW\PhpstanLaravel\Schema\MigrationFileParser;
use CalebDW\PhpstanLaravel\Schema\ModelSchema;
use CalebDW\PhpstanLaravel\Schema\SchemaDumpParser;
use CalebDW\PhpstanLaravel\Schema\Type\PostgresDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Schema\Type\SqlDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\ModelHelper;
use PHPStan\File\FileHelper as PHPStanFileHelper;
use PHPStan\Testing\PHPStanTestCase;

/** @mixin PHPStanTestCase */
trait HasDatabaseHelper
{
    private string $defaultConnection;
    private ModelSchema $modelDatabaseHelper;
    private ModelHelper $modelHelper;

    public function setUp(): void
    {
        $this->setUpHasDatabaseHelper();
    }

    private function setUpHasDatabaseHelper(): void
    {
        $this->modelHelper = new ModelHelper($this->createReflectionProvider());

        $this->modelDatabaseHelper = new ModelSchema(
            $this->getSquashedMigrationHelper(),
            $this->getMigrationHelper(),
            new ContainerHelper(),
        );

        $this->defaultConnection = $this->modelDatabaseHelper->getDefaultConnection();
    }

    /** @param  string[] $dirs */
    private function getMigrationHelper(array $dirs = ['foo'], bool $scan = true): MigrationFileParser
    {
        return new MigrationFileParser(
            self::getContainer()->getService('currentPhpVersionSimpleDirectParser'),
            $dirs,
            new FileHelper(
                self::getContainer()->getByType(PHPStanFileHelper::class),
            ),
            $scan,
            $this->modelHelper,
            self::createReflectionProvider(),
        );
    }

    /** @param  string[] $dirs */
    private function getSquashedMigrationHelper(array $dirs = ['foo'], bool $scan = true, string $driver = SqlParserManager::DRIVER_AUTO): SchemaDumpParser
    {
        return new SchemaDumpParser(
            $dirs,
            new FileHelper(
                self::getContainer()->getByType(PHPStanFileHelper::class),
            ),
            new SqlDataTypeToPhpTypeConverter(),
            new PostgresDataTypeToPhpTypeConverter(),
            new SqlParserManager($driver),
            $scan,
        );
    }
}
