<?php

declare(strict_types=1);

namespace Tests\Unit\Properties\Schema;

use CalebDW\PhpstanLaravel\Properties\Schema\PostgresDataTypeToPhpTypeConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PostgresDataTypeToPhpTypeConverterTest extends TestCase
{
    private PostgresDataTypeToPhpTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new PostgresDataTypeToPhpTypeConverter();
    }

    /**
     * The names are the canonical ones PostgreSQL resolves its spellings to, so
     * `integer` arrives as `int4` and `timestamp with time zone` as
     * `timestamptz`.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function typeProvider(): iterable
    {
        yield 'smallint' => ['int2', 'int'];
        yield 'integer' => ['int4', 'int'];
        yield 'bigint' => ['int8', 'int'];
        yield 'oid' => ['oid', 'int'];

        yield 'real' => ['float4', 'float'];
        yield 'double precision' => ['float8', 'float'];
        yield 'numeric' => ['numeric', 'float'];

        yield 'boolean' => ['bool', 'bool'];

        yield 'varchar' => ['varchar', 'string'];
        yield 'bpchar' => ['bpchar', 'string'];
        yield 'text' => ['text', 'string'];
        yield 'bytea' => ['bytea', 'string'];
        yield 'timestamp' => ['timestamp', 'string'];
        yield 'timestamptz' => ['timestamptz', 'string'];
        yield 'date' => ['date', 'string'];
        yield 'interval' => ['interval', 'string'];
        yield 'uuid' => ['uuid', 'string'];
        yield 'json' => ['json', 'string'];
        yield 'jsonb' => ['jsonb', 'string'];
        yield 'inet' => ['inet', 'string'];
        yield 'money' => ['money', 'string'];

        // PostgreSQL's bit is a bit string, unlike MySQL's, which is an integer.
        yield 'bit' => ['bit', 'string'];
        yield 'varbit' => ['varbit', 'string'];

        // Types installed by an extension, and anything else unrecognised, are
        // still readable as a string rather than being given up on as mixed.
        yield 'extension type' => ['hstore', 'string'];
        yield 'unknown type' => ['something_invented', 'string'];
    }

    #[Test]
    #[DataProvider('typeProvider')]
    public function it_converts_postgres_types(string $type, string $expected): void
    {
        $this->assertSame($expected, $this->converter->convert($type));
    }

    #[Test]
    public function it_describes_an_enum_as_a_union_of_its_labels(): void
    {
        $this->assertSame(
            "'active'|'closed'|'suspended'",
            $this->converter->convert(
                PostgresDataTypeToPhpTypeConverter::TYPE_ENUM,
                [],
                ['active', 'suspended', 'closed'],
            ),
        );
    }

    #[Test]
    public function it_falls_back_to_string_for_an_enum_without_labels(): void
    {
        $this->assertSame(
            'string',
            $this->converter->convert(PostgresDataTypeToPhpTypeConverter::TYPE_ENUM),
        );
    }

    /**
     * PDO renders an array column as its literal, `{a,b}`, rather than as a PHP
     * array, whatever the element type is.
     */
    #[Test]
    public function it_describes_an_array_column_as_a_string(): void
    {
        $options = [PostgresDataTypeToPhpTypeConverter::OPTION_ARRAY];

        $this->assertSame('string', $this->converter->convert('int4', $options));
        $this->assertSame('string', $this->converter->convert('text', $options));
    }
}
