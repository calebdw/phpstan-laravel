<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use CalebDW\PhpstanLaravel\Support\ModelCastHelper;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\VerbosityLevel;
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
}
