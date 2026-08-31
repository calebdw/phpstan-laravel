<?php

declare(strict_types=1);

namespace Tests\Unit\Concerns;

use CalebDW\PhpstanLaravel\Schema\MigrationHelper;
use CalebDW\PhpstanLaravel\Schema\ModelDatabaseHelper;
use CalebDW\PhpstanLaravel\Schema\SquashedMigrationHelper;
use CalebDW\PhpstanLaravel\Schema\Type\PostgresDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Schema\Type\SqlDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\ModelHelper;
use PHPStan\File\FileHelper as PHPStanFileHelper;
use PHPStan\Testing\PHPStanTestCase;

/** @mixin PHPStanTestCase */
trait HasDatabaseHelper
{
    private string $defaultConnection;
    private ModelDatabaseHelper $modelDatabaseHelper;
    private ModelHelper $modelHelper;

    public function setUp(): void
    {
        $this->modelHelper = new ModelHelper($this->createReflectionProvider());

        $this->modelDatabaseHelper = new ModelDatabaseHelper(
            $this->getSquashedMigrationHelper(),
            $this->getMigrationHelper(),
            new ContainerHelper(),
        );

        $this->defaultConnection = $this->modelDatabaseHelper->getDefaultConnection();
    }

    /** @param  string[] $dirs */
    private function getMigrationHelper(array $dirs = ['foo'], bool $scan = true): MigrationHelper
    {
        return new MigrationHelper(
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
    private function getSquashedMigrationHelper(array $dirs = ['foo'], bool $scan = true, string $driver = SqlParserManager::DRIVER_AUTO): SquashedMigrationHelper
    {
        return new SquashedMigrationHelper(
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
