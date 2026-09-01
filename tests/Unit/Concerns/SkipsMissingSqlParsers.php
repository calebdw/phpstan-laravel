<?php

declare(strict_types=1);

namespace Tests\Unit\Concerns;

use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserManager;

use function class_exists;
use function sprintf;

/**
 * Neither SQL parser is a hard requirement, and CI runs the suite with each of
 * them uninstalled in turn, so tests that need a real parser have to say so.
 */
trait SkipsMissingSqlParsers
{
    private const array PARSER_CLASSES = [
        SqlParserManager::DRIVER_IAMCAL => 'iamcal\SQLParser',
        SqlParserManager::DRIVER_PHPMYADMIN => 'PhpMyAdmin\SqlParser\Parser',
        SqlParserManager::DRIVER_POSTGRES => 'CalebDW\PgSchemaParser\PgDumpParser',
    ];

    private function skipUnlessParserInstalled(string ...$drivers): void
    {
        foreach ($drivers as $driver) {
            if (class_exists(self::PARSER_CLASSES[$driver])) {
                continue;
            }

            self::markTestSkipped(sprintf('The %s parser is not installed.', $driver));
        }
    }
}
