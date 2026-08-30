<?php

declare(strict_types=1);

namespace Tests\Type;

use App\Account;
use App\User;
use CalebDW\PhpstanLaravel\Properties\ModelDatabaseHelper;
use CalebDW\PhpstanLaravel\Support\ModelHelper;
use CalebDW\PhpstanLaravel\Types\ModelProperty\GenericModelPropertyType;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_map;
use function array_reverse;
use function implode;
use function sprintf;

class GenericModelPropertyTypeTest extends PHPStanTestCase
{
    #[DataProvider('dataEquals')]
    public function testEquals(callable $types, bool $expected): void
    {
        [$left, $right] = $types();

        $this->assertSame($expected, $left->equals($right));
        $this->assertSame($expected, $right->equals($left));
    }

    /** @return iterable<array{callable(mixed): GenericModelPropertyType[], bool}> */
    public static function dataEquals(): iterable
    {
        yield [
            static fn () => [
                static::genericPropertyType(User::class),
                static::genericPropertyType(User::class),
            ],
            true,
        ];

        yield [
            static fn () => [
                static::genericPropertyType(User::class),
                static::genericPropertyType(Account::class),
            ],
            false,
        ];
    }

    /** @param class-string<Type> $expectedTypeClass */
    #[DataProvider('dataUnion')]
    public function testUnion(
        callable $types,
        string $expectedTypeClass,
        string $expectedTypeDescription,
    ): void {
        $types = $types();

        $actualType            = TypeCombinator::union(...$types);
        $actualTypeDescription = $actualType->describe(VerbosityLevel::precise());

        $this->assertSame(
            $expectedTypeDescription,
            $actualTypeDescription,
            sprintf('union(%s)', implode(', ', array_map(
                static fn (Type $type): string => $type->describe(VerbosityLevel::precise()),
                $types,
            ))),
        );

        $this->assertInstanceOf($expectedTypeClass, $actualType);
    }

    /** @param class-string<Type> $expectedTypeClass */
    #[DataProvider('dataUnion')]
    public function testUnionInversed(
        callable $types,
        string $expectedTypeClass,
        string $expectedTypeDescription,
    ): void {
        $types                 = array_reverse($types());
        $actualType            = TypeCombinator::union(...$types);
        $actualTypeDescription = $actualType->describe(VerbosityLevel::precise());

        $this->assertSame(
            $expectedTypeDescription,
            $actualTypeDescription,
            sprintf('union(%s)', implode(', ', array_map(
                static fn (Type $type): string => $type->describe(VerbosityLevel::precise()),
                $types,
            ))),
        );
        $this->assertInstanceOf($expectedTypeClass, $actualType);
    }

    /** @return iterable<array{callable(mixed): Type[], class-string<Type>, string}> */
    public static function dataUnion(): iterable
    {
        yield [
            static fn () => [
                static::genericPropertyType(User::class),
                static::genericPropertyType(Account::class),
            ],
            UnionType::class,
            'model property of App\Account|model property of App\User',
        ];

        yield [
            static fn () => [
                static::genericPropertyType(User::class),
                new StringType(),
            ],
            StringType::class,
            'string',
        ];

        yield [
            static fn () => [
                static::genericPropertyType(User::class),
                new ConstantStringType('email'),
            ],
            GenericModelPropertyType::class,
            'model property of App\User',
        ];
    }

    /** @param class-string<Type> $expectedTypeClass */
    #[DataProvider('dataIntersect')]
    public function testIntersect(
        callable $types,
        string $expectedTypeClass,
        string $expectedTypeDescription,
    ): void {
        $types = $types();

        $actualType            = TypeCombinator::intersect(...$types);
        $actualTypeDescription = $actualType->describe(VerbosityLevel::precise());

        $this->assertSame($expectedTypeDescription, $actualTypeDescription);
        $this->assertInstanceOf($expectedTypeClass, $actualType);
    }

    /** @param class-string<Type> $expectedTypeClass */
    #[DataProvider('dataIntersect')]
    public function testIntersectInversed(
        callable $types,
        string $expectedTypeClass,
        string $expectedTypeDescription,
    ): void {
        $actualType            = TypeCombinator::intersect(...array_reverse($types()));
        $actualTypeDescription = $actualType->describe(VerbosityLevel::precise());

        $this->assertSame($expectedTypeDescription, $actualTypeDescription);
        $this->assertInstanceOf($expectedTypeClass, $actualType);
    }

    /** @return iterable<array{callable(mixed): Type[], class-string<Type>, string}> */
    public static function dataIntersect(): iterable
    {
        yield [
            static fn () => [
                static::genericPropertyType(User::class),
                static::genericPropertyType(Account::class),
            ],
            NeverType::class,
            '*NEVER*',
        ];

        yield [
            static fn () => [
                static::genericPropertyType(User::class),
                new StringType(),
            ],
            GenericModelPropertyType::class,
            'model property of App\User',
        ];

        yield [
            static fn () => [
                static::genericPropertyType(User::class),
                new ConstantStringType('email'),
            ],
            ConstantStringType::class,
            "'email'",
        ];
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../extension.neon',
        ];
    }

    private static function genericPropertyType(string $class): GenericModelPropertyType
    {
        return new GenericModelPropertyType(
            new ObjectType($class),
            self::getContainer()->getByType(ModelDatabaseHelper::class),
            new ModelHelper(self::createReflectionProvider()),
        );
    }
}
