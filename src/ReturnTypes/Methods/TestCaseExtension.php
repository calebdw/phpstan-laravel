<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Foundation\Testing\TestCase;
use Mockery\MockInterface;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_map;
use function in_array;

final class TestCaseExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private TypeHelper $typeHelper)
    {
    }

    public function getClass(): string
    {
        return TestCase::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['mock', 'partialMock', 'spy'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $abstract = $methodCall->getArg('abstract', 0)?->value;
        $mock     = new ObjectType(MockInterface::class);

        if ($abstract === null) {
            return null;
        }

        $abstracts = $this->typeHelper->constantStrings($scope->getType($abstract));

        if ($abstracts === []) {
            return $mock;
        }

        return TypeCombinator::union(...array_map(
            static fn ($a) => TypeCombinator::intersect($mock, new ObjectType($a)),
            $abstracts,
        ));
    }
}
