<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class EnumerableValuesAllExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'all';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        if (
            ! $methodCall->var instanceof MethodCall
            || ! $methodCall->var->name instanceof Identifier
            || $methodCall->var->name->name !== 'values'
        ) {
            return null;
        }

        $array = new ArrayType(
            new IntegerType(),
            $scope->getType($methodCall->var)->getIterableValueType(),
        );

        return TypeCombinator::intersect($array, new AccessoryArrayListType());
    }
}
