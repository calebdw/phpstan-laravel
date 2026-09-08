<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class ArrMapSpreadExtension implements DynamicStaticMethodReturnTypeExtension
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
        return $methodReflection->getName() === 'mapSpread';
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $arrayArg    = $methodCall->getArg('array', 0);
        $callbackArg = $methodCall->getArg('callback', 1);

        if ($arrayArg === null || $callbackArg === null) {
            return null;
        }

        $array = $scope->getType($arrayArg->value);
        $key   = $array->getIterableKeyType();
        $slots = $this->columnHelper->spreadSlots($array->getIterableValueType(), $key);

        if ($slots === null) {
            return null;
        }

        $mapped = $this->columnHelper->returnTypeFromCallable($callbackArg->value, $slots, $scope);

        if ($mapped === null) {
            return null;
        }

        return new ArrayType($key, $mapped);
    }
}
