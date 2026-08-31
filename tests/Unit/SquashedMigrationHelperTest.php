<?php

declare(strict_types=1);

namespace Tests\Unit;

use CalebDW\PhpstanLaravel\Schema\SchemaDumpParser;
use CalebDW\PhpstanLaravel\Sql\SqlParserFailure;
use CalebDW\PhpstanLaravel\Sql\SqlParserManager;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\Concerns\HasDatabaseHelper;
use Tests\Unit\Concerns\SkipsMissingSqlParsers;

use function array_keys;

#[CoversClass(SchemaDumpParser::class)]
class SquashedMigrationHelperTest extends PHPStanTestCase
{
    use HasDatabaseHelper;
    use SkipsMissingSqlParsers;

    /** @return iterable<string, array{string}> */
    public static function driverProvider(): iterable
    {
        yield 'phpmyadmin' => [SqlParserManager::DRIVER_PHPMYADMIN];
        yield 'iamcal' => [SqlParserManager::DRIVER_IAMCAL];
    }

    #[Test]
    #[DataProvider('driverProvider')]
    public function it_fails_loudly_when_a_dump_cannot_be_parsed(string $driver): void
    {
        $this->skipUnlessParserInstalled($driver);

        $this->expectException(SqlParserFailure::class);
        $this->expectExceptionMessageMatches('/Unable to parse the schema dump at .*unparseable_schema/');

        $this->getSquashedMigrationHelper(
            [__DIR__ . '/data/schema/unparseable_schema'],
            driver: $driver,
        )->parseSchemaDumps($this->modelDatabaseHelper);
    }

    #[Test]
    #[DataProvider('driverProvider')]
    public function it_keeps_the_same_table_separate_per_connection(string $driver): void
    {
        $this->skipUnlessParserInstalled($driver);

        $this->getSquashedMigrationHelper(
            [__DIR__ . '/data/schema/same_table_in_multiple_connections'],
            driver: $driver,
        )->parseSchemaDumps($this->modelDatabaseHelper);

        $connections = $this->modelDatabaseHelper->connections;

        $this->assertCount(2, $connections);
        $this->assertArrayHasKey('primary', $connections);
        $this->assertArrayHasKey('secondary', $connections);

        // Both connections define `accounts`, with different columns.
        $primary = $connections['primary']->tables['accounts'];
        $this->assertSame(['id', 'name'], array_keys($primary->columns));
        $this->assertSame('non-negative-int', $primary->columns['id']->readableType);

        $secondary = $connections['secondary']->tables['accounts'];
        $this->assertSame(['uuid', 'balance', 'closed_at'], array_keys($secondary->columns));
        $this->assertSame('string', $secondary->columns['uuid']->readableType);
        $this->assertSame('float', $secondary->columns['balance']->readableType);
        $this->assertTrue($secondary->columns['closed_at']->nullable);
    }

    #[Test]
    public function it_can_parse_basic_schema_in_different_formats(): void
    {
        $this->getSquashedMigrationHelper([__DIR__ . '/data/schema/basic_schema'])
            ->parseSchemaDumps($this->modelDatabaseHelper);

        $this->assertCount(2, $this->modelDatabaseHelper->connections);
        $this->assertArrayHasKey('default', $this->modelDatabaseHelper->connections);
        $this->assertArrayHasKey('nondefault', $this->modelDatabaseHelper->connections);

        foreach ($this->modelDatabaseHelper->connections as $connection) {
            $tables = $connection->tables;

            $this->assertCount(6, $tables['accounts']->columns);
            $this->assertSame(['id', 'name', 'active', 'description', 'created_at', 'updated_at'], array_keys($tables['accounts']->columns));
            $this->assertSame('non-negative-int', $tables['accounts']->columns['id']->readableType);
            $this->assertSame('string', $tables['accounts']->columns['name']->readableType);
            $this->assertSame('string', $tables['accounts']->columns['active']->readableType);
            $this->assertSame('string', $tables['accounts']->columns['description']->readableType);
            $this->assertSame('string', $tables['accounts']->columns['created_at']->readableType);
            $this->assertSame('string', $tables['accounts']->columns['updated_at']->readableType);
        }
    }

    #[Test]
    public function it_uses_the_last_definition_when_a_table_is_created_more_than_once(): void
    {
        // The dump drops and recreates `accounts`, so replaying it leaves only
        // the columns of the final CREATE TABLE statement.
        $this->getSquashedMigrationHelper([__DIR__ . '/data/schema/schema_with_create_statements_for_same_table'])
            ->parseSchemaDumps($this->modelDatabaseHelper);

        $this->assertCount(1, $this->modelDatabaseHelper->connections);
        $this->assertArrayHasKey('mysql', $this->modelDatabaseHelper->connections);
        $tables = $this->modelDatabaseHelper->connections['mysql']->tables;
        $this->assertCount(1, $tables);
        $this->assertArrayHasKey('accounts', $tables);
        $this->assertCount(1, $tables['accounts']->columns);
        $this->assertSame(['id'], array_keys($tables['accounts']->columns));
        $this->assertSame('non-negative-int', $tables['accounts']->columns['id']->readableType);
    }

    #[Test]
    public function it_can_find_schemas_with_different_extensions(): void
    {
        $this->getSquashedMigrationHelper([__DIR__ . '/data/schema/schema_with_nonstandard_name'])
            ->parseSchemaDumps($this->modelDatabaseHelper);

        $this->assertCount(1, $this->modelDatabaseHelper->connections);
        $this->assertArrayHasKey('pgsql', $this->modelDatabaseHelper->connections);
        $tables = $this->modelDatabaseHelper->connections['pgsql']->tables;
        $this->assertCount(2, $tables);
        $this->assertArrayHasKey('accounts', $tables);
        $this->assertCount(6, $tables['accounts']->columns);
        $this->assertSame(['id', 'name', 'active', 'description', 'created_at', 'updated_at'], array_keys($tables['accounts']->columns));
        $this->assertSame('non-negative-int', $tables['accounts']->columns['id']->readableType);
        $this->assertSame('string', $tables['accounts']->columns['name']->readableType);
        $this->assertSame('string', $tables['accounts']->columns['active']->readableType);
        $this->assertSame('string', $tables['accounts']->columns['description']->readableType);
        $this->assertSame('string', $tables['accounts']->columns['created_at']->readableType);
        $this->assertSame('string', $tables['accounts']->columns['updated_at']->readableType);
        $this->assertArrayHasKey('users', $tables);
        $this->assertCount(6, $tables['users']->columns);
        $this->assertSame(['id', 'name', 'active', 'description', 'created_at', 'updated_at'], array_keys($tables['users']->columns));
        $this->assertSame('non-negative-int', $tables['users']->columns['id']->readableType);
        $this->assertSame('string', $tables['users']->columns['name']->readableType);
        $this->assertSame('string', $tables['users']->columns['active']->readableType);
        $this->assertSame('string', $tables['users']->columns['description']->readableType);
        $this->assertSame('string', $tables['users']->columns['created_at']->readableType);
        $this->assertSame('string', $tables['users']->columns['updated_at']->readableType);
    }

    /**
     * The fixture is genuine `pg_dump --schema-only` output, which is what
     * `php artisan schema:dump` writes for a PostgreSQL connection.
     */
    #[Test]
    public function it_can_parse_a_postgres_schema_dump(): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_POSTGRES);

        $this->getSquashedMigrationHelper(
            [__DIR__ . '/data/schema/postgres_schema'],
            driver: SqlParserManager::DRIVER_POSTGRES,
        )->parseSchemaDumps($this->modelDatabaseHelper);

        $this->assertCount(1, $this->modelDatabaseHelper->connections);
        $this->assertArrayHasKey('pgsql', $this->modelDatabaseHelper->connections);

        $tables = $this->modelDatabaseHelper->connections['pgsql']->tables;

        // A table outside the default schema keeps its qualification, since
        // that is how a model addresses it.
        $this->assertSame(['posts', 'users', 'reporting.summaries'], array_keys($tables));

        $users = $tables['users']->columns;

        // bigserial becomes a bigint column plus a sequence, so the type left in
        // the dump is the integer.
        $this->assertSame('int', $users['id']->readableType);
        $this->assertFalse($users['id']->nullable);

        $this->assertSame('string', $users['name']->readableType);
        $this->assertSame('bool', $users['active']->readableType);
        $this->assertSame('int', $users['login_count']->readableType);
        $this->assertSame('float', $users['balance']->readableType);
        $this->assertSame('float', $users['rating']->readableType);

        // A PostgreSQL enum is a type rather than a column constraint, so its
        // labels come from the CREATE TYPE statement.
        $this->assertSame("'active'|'closed'|'suspended'", $users['status']->readableType);

        // A domain resolves to whatever it is built on.
        $this->assertSame('int', $users['quota']->readableType);

        // PDO returns an array column as its literal rather than a PHP array.
        $this->assertSame('string', $users['tags']->readableType);

        $this->assertSame('string', $users['meta']->readableType);
        $this->assertSame('string', $users['uuid']->readableType);
        $this->assertSame('string', $users['ip']->readableType);
        $this->assertSame('string', $users['created_at']->readableType);
        $this->assertTrue($users['created_at']->nullable);

        $this->assertSame('string', $tables['posts']->columns['published_at']->readableType);
    }

    #[Test]
    public function it_can_disable_schema_scanning(): void
    {
        $this->getSquashedMigrationHelper([__DIR__ . '/data/schema/basic_schema'], false)
            ->parseSchemaDumps($this->modelDatabaseHelper);

        $this->assertSame([], $this->modelDatabaseHelper->connections);
    }
}
