<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use CalebDW\PhpstanLaravel\Sql\IamcalSqlParser;
use CalebDW\PhpstanLaravel\Sql\PhpMyAdminSqlParser;
use CalebDW\PhpstanLaravel\Sql\SqlParser;
use CalebDW\PhpstanLaravel\Sql\SqlParserFailure;
use CalebDW\PhpstanLaravel\Sql\SqlParserManager;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Concerns\SkipsMissingSqlParsers;
use Throwable;

use function sprintf;

/**
 * SqlParser::parseTables() documents SqlParserFailure as the only exception it
 * throws, and SquashedMigrationHelper relies on that to report the offending
 * file. A library exception reaching the caller instead crashes the analysis.
 */
class SqlParserFailureContractTest extends TestCase
{
    use SkipsMissingSqlParsers;

    /** @return iterable<string, array{string}> */
    public static function hostileSchemaProvider(): iterable
    {
        // Fails while tokenising rather than while parsing: '[' is not a
        // character the MySQL lexer recognises.
        yield 'postgres array column' => ['CREATE TABLE users (id integer[] NOT NULL);'];

        // psql meta-commands, which pg_dump emits since PostgreSQL 18.
        yield 'psql meta command' => ["\\restrict abc\nCREATE TABLE users (id integer NOT NULL);"];

        // A multi-word type name, which the MySQL grammar cannot represent.
        yield 'multi word type' => ['CREATE TABLE users (created_at timestamp without time zone);'];

        yield 'nonsense type' => ['CREATE TABLE `users` (`id` !!!not a type!!! NOT NULL);'];
    }

    /** @return iterable<string, array{string}> */
    public static function driverProvider(): iterable
    {
        yield 'iamcal' => [SqlParserManager::DRIVER_IAMCAL];
        yield 'phpmyadmin' => [SqlParserManager::DRIVER_PHPMYADMIN];
    }

    #[Test]
    #[DataProvider('driverProvider')]
    public function it_never_lets_a_library_exception_escape(string $driver): void
    {
        $this->skipUnlessParserInstalled($driver);

        $parser = $this->parser($driver);

        foreach (self::hostileSchemaProvider() as $name => [$sql]) {
            try {
                $parser->parseTables($sql);
            } catch (SqlParserFailure) {
                continue;
            } catch (Throwable $exception) {
                self::fail(sprintf(
                    'The %s parser threw %s for the "%s" schema; expected %s.',
                    $driver,
                    $exception::class,
                    $name,
                    SqlParserFailure::class,
                ));
            }
        }

        // Parsing successfully is fine - the drivers disagree about which of
        // these they can read. Only the exception type is under test.
        $this->expectNotToPerformAssertions();
    }

    private function parser(string $driver): SqlParser
    {
        return match ($driver) {
            SqlParserManager::DRIVER_IAMCAL => new IamcalSqlParser(),
            SqlParserManager::DRIVER_PHPMYADMIN => new PhpMyAdminSqlParser(),
            default => throw new LogicException(sprintf('Unknown driver "%s".', $driver)),
        };
    }
}
