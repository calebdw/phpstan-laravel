<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use CalebDW\PhpstanLaravel\Sql\ColumnDefinition;
use CalebDW\PhpstanLaravel\Sql\PostgresSqlParser;
use CalebDW\PhpstanLaravel\Sql\SqlDialect;
use CalebDW\PhpstanLaravel\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Sql\TableDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Concerns\SkipsMissingSqlParsers;

use function array_map;

class PostgresSqlParserTest extends TestCase
{
    use SkipsMissingSqlParsers;

    protected function setUp(): void
    {
        $this->skipUnlessParserInstalled(SqlParserManager::DRIVER_POSTGRES);
    }

    #[Test]
    public function it_reports_the_postgres_dialect(): void
    {
        $column = $this->column('CREATE TABLE public.t (id bigint);', 't', 'id');

        $this->assertSame(SqlDialect::Postgres, $column->dialect);
    }

    /**
     * PostgreSQL resolves the SQL standard spellings to its own names, and the
     * dump parser reports those, so the type converter only has to know one
     * name per type.
     */
    #[Test]
    public function it_reports_canonical_type_names(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE public.t (
                a integer,
                b bigint,
                c double precision,
                d character varying(255),
                e timestamp(0) without time zone,
                f timestamp with time zone,
                g boolean
            );
            SQL;

        $types = [];

        foreach ($this->parse($sql)[0]->columns as $column) {
            $types[$column->name] = $column->type;
        }

        $this->assertSame([
            'a' => 'int4',
            'b' => 'int8',
            'c' => 'float8',
            'd' => 'varchar',
            'e' => 'timestamp',
            'f' => 'timestamptz',
            'g' => 'bool',
        ], $types);
    }

    #[Test]
    public function it_reads_nullability(): void
    {
        $sql = 'CREATE TABLE public.t (a integer NOT NULL, b integer);';

        $this->assertFalse($this->column($sql, 't', 'a')->nullable);
        $this->assertTrue($this->column($sql, 't', 'b')->nullable);
    }

    #[Test]
    public function it_reports_an_enum_column_with_its_labels(): void
    {
        $column = $this->column(
            <<<'SQL'
            CREATE TYPE public.status AS ENUM ('active', 'closed');
            CREATE TABLE public.t (state public.status);
            SQL,
            't',
            'state',
        );

        $this->assertSame('enum', $column->type);
        $this->assertSame(['active', 'closed'], $column->values);
    }

    #[Test]
    public function it_resolves_a_domain_to_the_type_it_wraps(): void
    {
        $column = $this->column(
            <<<'SQL'
            CREATE DOMAIN public.positive_int AS integer CHECK (VALUE > 0);
            CREATE TABLE public.t (quota public.positive_int);
            SQL,
            't',
            'quota',
        );

        $this->assertSame('int4', $column->type);
    }

    #[Test]
    public function it_flags_an_array_column(): void
    {
        $column = $this->column('CREATE TABLE public.t (tags text[]);', 't', 'tags');

        $this->assertSame('text', $column->type);
        $this->assertSame(['array'], $column->typeOptions);
    }

    /**
     * A model addresses a table in the default schema unqualified, and one
     * outside it by its qualified name, so the table names have to match.
     */
    #[Test]
    public function it_only_qualifies_tables_outside_the_default_schema(): void
    {
        $tables = $this->parse(<<<'SQL'
            CREATE TABLE public.users (id bigint);
            CREATE TABLE reporting.summaries (id bigint);
            SQL);

        $this->assertSame(
            ['users', 'reporting.summaries'],
            array_map(static fn (TableDefinition $table): string => $table->name, $tables),
        );
    }

    #[Test]
    public function it_ignores_statements_that_do_not_describe_a_table(): void
    {
        $tables = $this->parse(<<<'SQL'
            CREATE TABLE public.users (id bigint);
            CREATE VIEW public.active AS SELECT id FROM public.users;
            CREATE INDEX users_id_idx ON public.users USING btree (id);
            SQL);

        $this->assertCount(1, $tables);
    }

    /** @return list<TableDefinition> */
    private function parse(string $sql): array
    {
        return (new PostgresSqlParser())->parseTables($sql);
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
