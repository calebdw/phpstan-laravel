<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema\Sql;

use RuntimeException;

use function implode;
use function sprintf;

/**
 * Thrown when no usable parser is available for the configured driver.
 *
 * Neither parser is a hard requirement of this package, so that projects can
 * choose which one - and which license - they pull into their dependencies.
 */
final class SqlParserNotAvailable extends RuntimeException
{
    public static function noneInstalled(): self
    {
        return new self(
            'Parsing squashed schema dumps requires an SQL parser, but none is installed.'
            . ' Install one of the following as a development dependency:' . "\n"
            . '  composer require --dev iamcal/sql-parser      (MIT)' . "\n"
            . '  composer require --dev phpmyadmin/sql-parser  (GPL-2.0-or-later)' . "\n"
            . '  composer require --dev calebdw/pg-schema-parser (MIT, PostgreSQL)' . "\n"
            . 'Alternatively, set laravel.scanSchema to false to skip schema dumps entirely.',
        );
    }

    public static function driverNotInstalled(string $driver, string $package, string $license): self
    {
        return new self(sprintf(
            'The "%s" SQL parser driver requires the %s package (%s), which is not installed.'
                . ' Install it with: composer require --dev %s',
            $driver,
            $package,
            $license,
            $package,
        ));
    }

    /** @param list<string> $available */
    public static function unknownDriver(string $driver, array $available): self
    {
        return new self(sprintf(
            'Unsupported SQL parser driver "%s". Available drivers: %s.',
            $driver,
            implode(', ', $available),
        ));
    }
}
