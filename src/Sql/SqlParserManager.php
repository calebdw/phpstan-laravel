<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Sql;

use Closure;

use function array_keys;
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
    ];

    /**
     * Order in which the `auto` driver prefers the available parsers.
     *
     * phpMyAdmin understands more of the MySQL dialect, so it wins when both
     * are installed.
     */
    private const array AUTO_PREFERENCE = [self::DRIVER_PHPMYADMIN, self::DRIVER_IAMCAL];

    /** @var array<string, Closure(): SqlParser> */
    private array $customCreators = [];

    /** @var array<string, SqlParser> */
    private array $resolved = [];

    /** @var Closure(class-string): bool */
    private Closure $classExists;

    /** @param (Closure(class-string): bool)|null $classExists */
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

    /**
     * Register a parser of your own, or replace one of the built-in drivers.
     *
     * @param Closure(): SqlParser $callback
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;

        unset($this->resolved[$driver]);

        return $this;
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
        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])();
        }

        return match ($driver) {
            self::DRIVER_AUTO => $this->createAutoDriver(),
            self::DRIVER_IAMCAL => $this->createIamcalDriver(),
            self::DRIVER_PHPMYADMIN => $this->createPhpMyAdminDriver(),
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
            ...array_keys(self::REQUIREMENTS),
            ...array_keys($this->customCreators),
        ];
    }
}
