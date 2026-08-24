<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties\Schema;

use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

use function array_map;
use function in_array;

final class SqlDataTypeToPhpTypeConverter implements DataTypeToPhpTypeConverter
{
    /** @inheritDoc */
    public function convert(string $type, array $options = [], array $values = []): string
    {
        return match ($type) {
            'CHAR', 'CHARACTER', 'VARCHAR', 'CHARACTER VARYING', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BINARY', 'VARBINARY', 'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB', 'JSON' => 'string',
            'BIT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'YEAR' => in_array('unsigned', $options, true) ? 'non-negative-int' : 'int',
            'DECIMAL', 'DEC', 'NUMERIC', 'FIXED', 'FLOAT', 'DOUBLE', 'DOUBLE PRECISION', 'REAL' => 'float',
            'BOOL', 'BOOLEAN' => 'bool',
            'ENUM' => $values === [] ? 'string' : TypeCombinator::union(...array_map(
                static fn (string $value): ConstantStringType => new ConstantStringType($value),
                $values,
            ))->describe(VerbosityLevel::precise()),
            default => 'mixed',
        };
    }
}
