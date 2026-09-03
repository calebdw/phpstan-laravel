<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class ArrPluckExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'pluck';
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $arrayArg = $methodCall->getArg('array', 0);
        $valueArg = $methodCall->getArg('value', 1);
        $keyArg   = $methodCall->getArg('key', 2);

        if ($arrayArg === null || $valueArg === null) {
            return null;
        }

        $from = $scope->getType($arrayArg->value)->getIterableValueType();

        $array = $this->columnHelper->getArrayType($from, $valueArg, $keyArg, $scope);

        if ($keyArg !== null) {
            return $array;
        }

        // Without a key Arr::pluck appends, so the result is a list.
        return TypeCombinator::intersect($array, new AccessoryArrayListType());
    }
}
