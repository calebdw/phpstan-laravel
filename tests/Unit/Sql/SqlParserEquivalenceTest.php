<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use CalebDW\PhpstanLaravel\Sql\IamcalSqlParser;
use CalebDW\PhpstanLaravel\Sql\PhpMyAdminSqlParser;
use CalebDW\PhpstanLaravel\Sql\SqlParser;
use CalebDW\PhpstanLaravel\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Sql\TableDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Concerns\SkipsMissingSqlParsers;

use function array_map;
use function file_get_contents;
use function usort;

/**
 * Both parsers are optional and interchangeable, so they have to agree on what
 * a schema dump means. Anything they disagree about is a bug in one of them.
 */
class SqlParserEquivalenceTest extends TestCase
{
    use SkipsMissingSqlParsers;

    /** @return iterable<string, array{string}> */
    public static function schemaProvider(): iterable
    {
        yield 'basic schema' => [__DIR__ . '/../data/schema/basic_schema/default-schema.dump'];
        yield 'duplicate create statements' => [__DIR__ . '/../data/schema/schema_with_create_statements_for_same_table/mysql-schema.dump'];
        yield 'nonstandard name' => [__DIR__ . '/../data/schema/schema_with_nonstandard_name/pgsql.sql'];
    }

    #[Test]
    #[DataProvider('schemaProvider')]
    public function both_parsers_describe_the_same_schema(string $path): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_IAMCAL, SqlParserManager::DRIVER_PHPMYADMIN);

        $sql = file_get_contents($path);
        self::assertNotFalse($sql);

        $this->assertSame(
            $this->normalize(new PhpMyAdminSqlParser(), $sql),
            $this->normalize(new IamcalSqlParser(), $sql),
        );
    }

    /**
     * Dumps written by hand state NULL and NOT NULL less consistently than
     * mysqldump does, which is where the two parsers are most likely to drift
     * apart on nullability.
     */
    #[Test]
    public function both_parsers_agree_on_columns_without_a_null_constraint(): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_IAMCAL, SqlParserManager::DRIVER_PHPMYADMIN);

        $sql = <<<'SQL'
            CREATE TABLE `t` (
                `id` int NOT NULL AUTO_INCREMENT,
                `action_value` float DEFAULT 0,
                `fba_vat` float DEFAULT NULL,
                `not_null_col` int NOT NULL DEFAULT 1,
                `bare_varchar` varchar(255),
                `body` text,
                PRIMARY KEY (`id`)
            );
            SQL;

        $this->assertSame(
            $this->normalize(new PhpMyAdminSqlParser(), $sql),
            $this->normalize(new IamcalSqlParser(), $sql),
        );
    }

    /** @return list<array{name: string, columns: list<array{name: string, type: string, nullable: bool}>}> */
    private function normalize(SqlParser $parser, string $sql): array
    {
        $tables = array_map(
            static fn (TableDefinition $table): array => [
                'name' => $table->name,
                'columns' => array_map(
                    static fn ($column): array => [
                        'name' => $column->name,
                        'type' => $column->type,
                        'nullable' => $column->nullable,
                    ],
                    $table->columns,
                ),
            ],
            $parser->parseTables($sql),
        );

        usort($tables, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $tables;
    }
}
