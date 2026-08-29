<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use CalebDW\PhpstanLaravel\Sql\ColumnDefinition;
use CalebDW\PhpstanLaravel\Sql\IamcalSqlParser;
use CalebDW\PhpstanLaravel\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Sql\TableDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Concerns\SkipsMissingSqlParsers;

class IamcalSqlParserTest extends TestCase
{
    use SkipsMissingSqlParsers;

    protected function setUp(): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_IAMCAL);
    }

    #[Test]
    public function it_reads_explicit_nullability(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `t` (
                `a` int NOT NULL,
                `b` varchar(255) NULL
            );
            SQL;

        $this->assertFalse($this->column($sql, 't', 'a')->nullable);
        $this->assertTrue($this->column($sql, 't', 'b')->nullable);
    }

    /**
     * A column that states neither NULL nor NOT NULL is nullable, whatever it
     * defaults to. Reading a DEFAULT as a nullability constraint would make
     * `float DEFAULT 0` non-nullable and drop null from the property type.
     */
    #[Test]
    public function it_treats_a_column_without_a_null_constraint_as_nullable(): void
    {
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

        $this->assertFalse($this->column($sql, 't', 'id')->nullable);
        $this->assertTrue($this->column($sql, 't', 'action_value')->nullable);
        $this->assertTrue($this->column($sql, 't', 'fba_vat')->nullable);
        $this->assertFalse($this->column($sql, 't', 'not_null_col')->nullable);
        $this->assertTrue($this->column($sql, 't', 'bare_varchar')->nullable);
        $this->assertTrue($this->column($sql, 't', 'body')->nullable);
    }

    #[Test]
    public function it_reports_the_columns_of_a_create_table_statement(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `accounts` (
                `id` INT NOT NULL,
                `name` VARCHAR(255)
            );
            SQL;

        $tables = $this->parse($sql);

        $this->assertCount(1, $tables);
        $this->assertSame('accounts', $tables[0]->name);
        $this->assertSame('INT', $this->column($sql, 'accounts', 'id')->type);
        $this->assertSame('VARCHAR', $this->column($sql, 'accounts', 'name')->type);
    }

    /** @return list<TableDefinition> */
    private function parse(string $sql): array
    {
        return (new IamcalSqlParser())->parseTables($sql);
    }

    private function column(string $sql, string $table, string $column): ColumnDefinition
    {
        foreach ($this->parse($sql) as $definition) {
            if ($definition->name !== $table) {
                continue;
            }

            foreach ($definition->columns as $found) {
                if ($found->name === $column) {
                    return $found;
                }
            }
        }

        self::fail('Column ' . $table . '.' . $column . ' was not found.');
    }
}
