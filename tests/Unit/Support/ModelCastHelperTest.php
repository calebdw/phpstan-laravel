<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use CalebDW\PhpstanLaravel\Support\ModelCastHelper;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class ModelCastHelperTest extends PHPStanTestCase
{
    #[Test]
    public function it_preserves_integer_ranges_for_integer_casts(): void
    {
        $helper = (new ReflectionClass(ModelCastHelper::class))->newInstanceWithoutConstructor();
        $type   = IntegerRangeType::createAllGreaterThanOrEqualTo(0);

        self::assertSame('int<0, max>', $helper->getReadableType('int', $type)->describe(VerbosityLevel::precise()));
        self::assertSame('int<0, max>', $helper->getWriteableType('integer', $type)->describe(VerbosityLevel::precise()));
    }

    #[Test]
    #[DataProvider('dateCasts')]
    public function it_accepts_laravel_date_inputs_for_date_casts(string $cast): void
    {
        $helper = (new ReflectionClass(ModelCastHelper::class))->newInstanceWithoutConstructor();
        $type   = $helper->getWriteableType($cast, new StringType());

        foreach ([DateTimeInterface::class, DateTime::class, DateTimeImmutable::class] as $class) {
            self::assertTrue($type->isSuperTypeOf(new ObjectType($class))->yes());
        }

        self::assertTrue($type->isSuperTypeOf(new StringType())->yes());
        self::assertTrue($type->isSuperTypeOf(new IntegerType())->yes());
        self::assertTrue($type->isSuperTypeOf(new FloatType())->yes());
        self::assertTrue($type->isSuperTypeOf(new BooleanType())->no());
        self::assertTrue($type->isSuperTypeOf(new ArrayType(new MixedType(), new MixedType()))->no());
    }

    /** @return iterable<string, array{string}> */
    public static function dateCasts(): iterable
    {
        foreach (['date', 'datetime', 'immutable_date', 'immutable_datetime'] as $cast) {
            yield $cast => [$cast];
            yield $cast . ' with format' => [$cast . ':Y-m-d H:i:s'];
        }
    }
}
