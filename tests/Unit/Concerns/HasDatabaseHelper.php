<?php

declare(strict_types=1);

namespace Tests\Unit\Concerns;

use CalebDW\PhpstanLaravel\Internal\FileHelper;
use CalebDW\PhpstanLaravel\Properties\MigrationHelper;
use CalebDW\PhpstanLaravel\Properties\ModelDatabaseHelper;
use CalebDW\PhpstanLaravel\Properties\Schema\PhpMyAdminDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Properties\SquashedMigrationHelper;
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
        );

        $this->defaultConnection = $this->modelDatabaseHelper->getDefaultConnection();
    }

    /** @param  string[] $dirs */
    private function getMigrationHelper(array $dirs = ['foo'], bool $disableScan = false): MigrationHelper
    {
        return new MigrationHelper(
            self::getContainer()->getService('currentPhpVersionSimpleDirectParser'),
            $dirs,
            new FileHelper(
                self::getContainer()->getByType(PHPStanFileHelper::class),
            ),
            $disableScan,
            $this->modelHelper,
            self::createReflectionProvider(),
        );
    }

    /** @param  string[] $dirs */
    private function getSquashedMigrationHelper(array $dirs = ['foo'], bool $disableScan = false): SquashedMigrationHelper
    {
        return new SquashedMigrationHelper(
            $dirs,
            new FileHelper(
                self::getContainer()->getByType(PHPStanFileHelper::class),
            ),
            new PhpMyAdminDataTypeToPhpTypeConverter(),
            $disableScan,
        );
    }
}
