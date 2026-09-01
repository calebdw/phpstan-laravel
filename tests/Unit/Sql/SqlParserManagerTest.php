<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use CalebDW\PhpstanLaravel\Schema\Sql\IamcalSqlParser;
use CalebDW\PhpstanLaravel\Schema\Sql\PhpMyAdminSqlParser;
use CalebDW\PhpstanLaravel\Schema\Sql\PostgresSqlParser;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserNotAvailable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Concerns\SkipsMissingSqlParsers;

use function in_array;

class SqlParserManagerTest extends TestCase
{
    use SkipsMissingSqlParsers;

    /** @param list<string> $installed */
    private function manager(string $driver = SqlParserManager::DRIVER_AUTO, array|null $installed = null): SqlParserManager
    {
        if ($installed === null) {
            return new SqlParserManager($driver);
        }

        return new SqlParserManager(
            $driver,
            static fn (string $class): bool => in_array($class, $installed, true),
        );
    }

    #[Test]
    public function it_reports_the_configured_default_driver(): void
    {
        $this->assertSame('iamcal', $this->manager('iamcal')->getDefaultDriver());
    }

    #[Test]
    public function it_resolves_each_driver_explicitly(): void
    {
        $this->skipUnlessParserInstalled(
            SqlParserManager::DRIVER_IAMCAL,
            SqlParserManager::DRIVER_PHPMYADMIN,
            SqlParserManager::DRIVER_POSTGRES,
        );

        $manager = $this->manager();

        $this->assertInstanceOf(IamcalSqlParser::class, $manager->driver('iamcal'));
        $this->assertInstanceOf(PhpMyAdminSqlParser::class, $manager->driver('phpmyadmin'));
        $this->assertInstanceOf(PostgresSqlParser::class, $manager->driver('postgres'));
    }

    #[Test]
    public function it_prefers_phpmyadmin_when_both_are_installed(): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_IAMCAL, SqlParserManager::DRIVER_PHPMYADMIN);

        $this->assertInstanceOf(PhpMyAdminSqlParser::class, $this->manager()->driver());
    }

    #[Test]
    public function it_falls_back_to_iamcal_when_phpmyadmin_is_missing(): void
    {
        $manager = $this->manager(installed: ['iamcal\SQLParser']);

        $this->assertInstanceOf(IamcalSqlParser::class, $manager->driver());
    }

    #[Test]
    public function it_uses_postgres_when_it_is_the_only_installed_parser(): void
    {
        $manager = $this->manager(installed: ['CalebDW\PgSchemaParser\PgDumpParser']);

        $this->assertInstanceOf(PostgresSqlParser::class, $manager->driver());
    }

    #[Test]
    public function it_fails_with_installation_instructions_when_no_parser_is_installed(): void
    {
        $manager = $this->manager(installed: []);

        $this->expectException(SqlParserNotAvailable::class);
        $this->expectExceptionMessageMatches('/requires an SQL parser, but none is installed/');
        $this->expectExceptionMessageMatches('/iamcal\/sql-parser/');
        $this->expectExceptionMessageMatches('/phpmyadmin\/sql-parser/');
        $this->expectExceptionMessageMatches('/calebdw\/pg-schema-parser/');

        $manager->driver();
    }

    #[Test]
    public function it_fails_when_the_requested_driver_is_not_installed(): void
    {
        $manager = $this->manager(installed: ['iamcal\SQLParser']);

        $this->expectException(SqlParserNotAvailable::class);
        $this->expectExceptionMessageMatches('/"phpmyadmin" SQL parser driver requires the phpmyadmin\/sql-parser package \(GPL-2\.0-or-later\)/');

        $manager->driver('phpmyadmin');
    }

    #[Test]
    public function it_does_not_silently_fall_back_when_an_explicit_driver_is_unavailable(): void
    {
        // phpmyadmin is installed and would satisfy `auto`, but the explicit
        // request for iamcal must fail rather than quietly use the other one.
        $manager = $this->manager('iamcal', installed: ['PhpMyAdmin\SqlParser\Parser']);

        $this->expectException(SqlParserNotAvailable::class);
        $this->expectExceptionMessageMatches('/"iamcal" SQL parser driver requires the iamcal\/sql-parser package \(MIT\)/');

        $manager->driver();
    }

    #[Test]
    public function it_rejects_an_unknown_driver(): void
    {
        $this->expectException(SqlParserNotAvailable::class);
        $this->expectExceptionMessageMatches('/Unsupported SQL parser driver "sqlite"/');

        $this->manager()->driver('sqlite');
    }

    #[Test]
    public function it_reuses_resolved_drivers(): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_IAMCAL);

        $manager = $this->manager();

        $this->assertSame($manager->driver('iamcal'), $manager->driver('iamcal'));
    }

    #[Test]
    public function it_lists_the_installed_drivers(): void
    {
        $this->skipUnlessParserInstalled(
            SqlParserManager::DRIVER_IAMCAL,
            SqlParserManager::DRIVER_PHPMYADMIN,
            SqlParserManager::DRIVER_POSTGRES,
        );

        $this->assertSame(['iamcal', 'phpmyadmin', 'postgres'], $this->manager()->availableDrivers());
        $this->assertSame([], $this->manager(installed: [])->availableDrivers());
        $this->assertSame(['phpmyadmin'], $this->manager(installed: ['PhpMyAdmin\SqlParser\Parser'])->availableDrivers());
        $this->assertSame(
            ['postgres'],
            $this->manager(installed: ['CalebDW\PgSchemaParser\PgDumpParser'])->availableDrivers(),
        );
    }
}
