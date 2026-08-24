<?php

declare(strict_types=1);

namespace Tests\Unit\Properties\Schema;

use CalebDW\PhpstanLaravel\Properties\Schema\SqlDataTypeToPhpTypeConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SqlDataTypeToPhpTypeConverterTest extends TestCase
{
    /**
     * The type names are whatever the parsers report, which is the spelling used
     * in the dump. A PostgreSQL dump writes the SQL standard names in full, so
     * `CHAR` and `VARCHAR` alone do not cover it.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function characterTypeProvider(): iterable
    {
        yield 'char' => ['CHAR', 'string'];
        yield 'character' => ['CHARACTER', 'string'];
        yield 'varchar' => ['VARCHAR', 'string'];
        yield 'character varying' => ['CHARACTER VARYING', 'string'];
    }

    #[Test]
    #[DataProvider('characterTypeProvider')]
    public function it_converts_character_types_to_string(string $type, string $expected): void
    {
        $this->assertSame($expected, (new SqlDataTypeToPhpTypeConverter())->convert($type));
    }

    #[Test]
    public function it_falls_back_to_mixed_for_unknown_types(): void
    {
        $this->assertSame('mixed', (new SqlDataTypeToPhpTypeConverter())->convert('JSONB'));
    }
}
