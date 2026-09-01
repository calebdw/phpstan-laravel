<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema\Sql;

use Closure;

use function class_exists;

/**
 * Resolves the SQL parser used to read squashed schema dumps.
 *
 * Follows Laravel's manager/driver pattern, but deliberately does not extend
 * Illuminate\Support\Manager: that reads its driver configuration from the
 * analysed application's config repository, whereas the driver here comes from
 * this extension's own PHPStan configuration.
 */
final class SqlParserManager
{
    public const string DRIVER_AUTO       = 'auto';
    public const string DRIVER_IAMCAL     = 'iamcal';
    public const string DRIVER_PHPMYADMIN = 'phpmyadmin';
    public const string DRIVER_POSTGRES   = 'postgres';

    private const array REQUIREMENTS = [
        self::DRIVER_IAMCAL => [
            'package' => 'iamcal/sql-parser',
            'license' => 'MIT',
            'class' => 'iamcal\SQLParser',
        ],
        self::DRIVER_PHPMYADMIN => [
            'package' => 'phpmyadmin/sql-parser',
            'license' => 'GPL-2.0-or-later',
            'class' => 'PhpMyAdmin\SqlParser\Parser',
        ],
        self::DRIVER_POSTGRES => [
            'package' => 'calebdw/pg-schema-parser',
            'license' => 'MIT',
            'class' => 'CalebDW\PgSchemaParser\PgDumpParser',
        ],
    ];

    /**
     * Order in which the `auto` driver prefers the available parsers.
     *
     * phpMyAdmin understands more of the MySQL dialect, so it wins when both
     * MySQL parsers are installed. PostgreSQL is last because its presence
     * cannot disambiguate the dump dialect when another parser is installed.
     */
    private const array AUTO_PREFERENCE = [
        self::DRIVER_PHPMYADMIN,
        self::DRIVER_IAMCAL,
        self::DRIVER_POSTGRES,
    ];

    /** @var array<string, SqlParser> */
    private array $resolved = [];

    /**
     * Not class-string: the point of the check is that the class may not
     * exist, and a driver's class is only a known class-string on a machine
     * where that optional parser happens to be installed.
     *
     * @var Closure(string): bool
     */
    private Closure $classExists;

    /** @param (Closure(string): bool)|null $classExists */
    public function __construct(
        private string $defaultDriver = self::DRIVER_AUTO,
        Closure|null $classExists = null,
    ) {
        $this->classExists = $classExists ?? static fn (string $class): bool => class_exists($class);
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /** @throws SqlParserNotAvailable */
    public function driver(string|null $driver = null): SqlParser
    {
        $driver ??= $this->getDefaultDriver();

        return $this->resolved[$driver] ??= $this->resolve($driver);
    }

    /** @return list<string> */
    public function availableDrivers(): array
    {
        $drivers = [];

        foreach (self::REQUIREMENTS as $driver => $requirement) {
            if (! ($this->classExists)($requirement['class'])) {
                continue;
            }

            $drivers[] = $driver;
        }

        return $drivers;
    }

    /** @throws SqlParserNotAvailable */
    private function resolve(string $driver): SqlParser
    {
        return match ($driver) {
            self::DRIVER_AUTO => $this->createAutoDriver(),
            self::DRIVER_IAMCAL => $this->createIamcalDriver(),
            self::DRIVER_PHPMYADMIN => $this->createPhpMyAdminDriver(),
            self::DRIVER_POSTGRES => $this->createPostgresDriver(),
            default => throw SqlParserNotAvailable::unknownDriver($driver, $this->knownDrivers()),
        };
    }

    /** @throws SqlParserNotAvailable */
    private function createAutoDriver(): SqlParser
    {
        foreach (self::AUTO_PREFERENCE as $driver) {
            if (! ($this->classExists)(self::REQUIREMENTS[$driver]['class'])) {
                continue;
            }

            return $this->driver($driver);
        }

        throw SqlParserNotAvailable::noneInstalled();
    }

    /** @throws SqlParserNotAvailable */
    private function createIamcalDriver(): SqlParser
    {
        $this->ensureInstalled(self::DRIVER_IAMCAL);

        return new IamcalSqlParser();
    }

    /** @throws SqlParserNotAvailable */
    private function createPhpMyAdminDriver(): SqlParser
    {
        $this->ensureInstalled(self::DRIVER_PHPMYADMIN);

        return new PhpMyAdminSqlParser();
    }

    /** @throws SqlParserNotAvailable */
    private function createPostgresDriver(): SqlParser
    {
        $this->ensureInstalled(self::DRIVER_POSTGRES);

        return new PostgresSqlParser();
    }

    /** @throws SqlParserNotAvailable */
    private function ensureInstalled(string $driver): void
    {
        $requirement = self::REQUIREMENTS[$driver];

        if (($this->classExists)($requirement['class'])) {
            return;
        }

        throw SqlParserNotAvailable::driverNotInstalled(
            $driver,
            $requirement['package'],
            $requirement['license'],
        );
    }

    /** @return list<string> */
    private function knownDrivers(): array
    {
        return [
            self::DRIVER_AUTO,
            self::DRIVER_IAMCAL,
            self::DRIVER_PHPMYADMIN,
            self::DRIVER_POSTGRES,
        ];
    }
}
