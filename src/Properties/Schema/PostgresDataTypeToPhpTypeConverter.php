<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Properties\Schema;

use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

use function array_map;
use function in_array;

#[AutowiredService]
final class PostgresDataTypeToPhpTypeConverter implements DataTypeToPhpTypeConverter
{
    public const string OPTION_ARRAY = 'array';

    public const string TYPE_ENUM = 'enum';

    /** @inheritDoc */
    public function convert(string $type, array $options = [], array $values = []): string
    {
        if (in_array(self::OPTION_ARRAY, $options, true)) {
            return 'string';
        }

        return match ($type) {
            'int2', 'int4', 'int8', 'oid' => 'int',
            'float4', 'float8', 'numeric' => 'float',
            'bool' => 'bool',
            self::TYPE_ENUM => $values === [] ? 'string' : TypeCombinator::union(...array_map(
                static fn (string $value): ConstantStringType => new ConstantStringType($value),
                $values,
            ))->describe(VerbosityLevel::precise()),
            default => 'string',
        };
    }
}
