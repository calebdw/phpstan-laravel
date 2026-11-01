<?php

declare(strict_types=1);

namespace Tests\Unit;

use CalebDW\PhpstanLaravel\Schema\MigrationSchemaParser;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Unit\Concerns\HasDatabaseHelper;
use Tests\Unit\Concerns\SkipsMissingSqlParsers;

use function array_keys;

class MigrationSqlTest extends PHPStanTestCase
{
    use HasDatabaseHelper;
    use SkipsMissingSqlParsers;

    /** @return iterable<string, array{string}> */
    public static function drivers(): iterable
    {
        yield 'phpmyadmin' => [SqlParserManager::DRIVER_PHPMYADMIN];
        yield 'iamcal' => [SqlParserManager::DRIVER_IAMCAL];
        yield 'postgres' => [SqlParserManager::DRIVER_POSTGRES];
    }

    #[DataProvider('drivers')]
    public function testReadsLiteralSqlOnEveryConnection(string $driver): void
    {
        $this->skipUnlessParserInstalled($driver);
        $this->parse(<<<'PHP'
            <?php

            use Illuminate\Support\Facades\DB as Database;
            use Illuminate\Support\Facades\Schema;

            class Migration
            {
                protected $connection = 'migration';

                public function up(): void
                {
                    Database::statement('CREATE TABLE raw_rows (id BIGINT NOT NULL, name VARCHAR(80));');
                    Database::unprepared('CREATE TABLE raw_more (id INTEGER NOT NULL); CREATE TABLE raw_last (id INTEGER);');
                    Database::connection()->statement('CREATE TABLE default_rows (id INTEGER);');
                    Database::connection(null)->statement('CREATE TABLE null_rows (id INTEGER);');
                    Database::connection($unknown)->statement('CREATE TABLE ignored_connection (id INTEGER);');
                    Database::connection(name: 'secondary')->statement(query: 'CREATE TABLE raw_rows (title VARCHAR(40) NOT NULL);');
                    Database::connection('third')->unprepared('CREATE TABLE raw_rows (amount DECIMAL(10, 2));');
                    Schema::create('existing_rows', fn ($table) => $table->integer('existing'));
                    Database::statement('CREATE TABLE existing_rows (ignored INTEGER);');
                    Database::statement('SELECT 1');
                    Database::statement($dynamicSql);
                    Database::statement(...);
                    Database::select('CREATE TABLE ignored_select (id INTEGER);');
                    OtherDatabase::statement('CREATE TABLE ignored_class (id INTEGER);');
                }
            }
            PHP, $driver);

        $connections = $this->modelDatabaseHelper->connections;
        $tables      = $connections['migration']->tables;

        self::assertSame(['raw_rows', 'raw_more', 'raw_last', 'default_rows', 'null_rows', 'existing_rows'], array_keys($tables));
        self::assertSame('int', $tables['raw_rows']->columns['id']->readableType);
        self::assertFalse($tables['raw_rows']->columns['id']->nullable);
        self::assertSame('string', $tables['raw_rows']->columns['name']->readableType);
        self::assertTrue($tables['raw_rows']->columns['name']->nullable);
        self::assertSame(['existing'], array_keys($tables['existing_rows']->columns));
        self::assertSame(['title'], array_keys($connections['secondary']->tables['raw_rows']->columns));
        self::assertSame('float', $connections['third']->tables['raw_rows']->columns['amount']->readableType);
    }

    public function testSelectsPostgresForAnAutomaticConnectionDriver(): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_POSTGRES);
        $config                                      = self::getContainer()->getByType(ContainerHelper::class)->resolve('config');
        $before                                      = $config['database.connections'];
        $config['database.connections.sql_postgres'] = ['driver' => 'pgsql'];

        try {
            $this->parse(<<<'PHP'
                <?php

                use Illuminate\Support\Facades\DB;

                class Migration
                {
                    public function up(): void
                    {
                        DB::connection('sql_postgres')->unprepared('CREATE TABLE private.raw_rows (id UUID NOT NULL, active BOOLEAN);');
                    }
                }
                PHP, SqlParserManager::DRIVER_AUTO);
        } finally {
            $config['database.connections'] = $before;
        }

        $columns = $this->modelDatabaseHelper->connections['sql_postgres']->tables['private.raw_rows']->columns;

        self::assertSame('string', $columns['id']->readableType);
        self::assertSame('bool', $columns['active']->readableType);
        self::assertTrue($columns['active']->nullable);
    }

    public function testIgnoresSqlParserFailures(): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_IAMCAL);
        $this->parse(<<<'PHP'
            <?php

            use Illuminate\Support\Facades\DB;

            class Migration
            {
                public function up(): void
                {
                    DB::statement('CREATE TABLE invalid_rows (id !!!not a type!!! NOT NULL);');
                }
            }
            PHP, SqlParserManager::DRIVER_IAMCAL);

        self::assertSame([], $this->modelDatabaseHelper->connections[$this->defaultConnection]->tables);
    }

    private function parse(string $source, string $driver): void
    {
        $parser = self::getContainer()->getService('currentPhpVersionSimpleDirectParser');
        $schema = new MigrationSchemaParser(
            $this->modelDatabaseHelper,
            $this->modelHelper,
            $this->createReflectionProvider(),
            self::getContainer()->getByType(InitializerExprTypeResolver::class),
            $this->getSqlSchemaHelper($driver),
        );

        $schema->addStatements($parser->parseString($source));
    }
}
