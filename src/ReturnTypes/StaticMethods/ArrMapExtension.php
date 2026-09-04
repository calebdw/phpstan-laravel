<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function in_array;

final class ArrMapExtension implements DynamicStaticMethodReturnTypeExtension
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
        return in_array($methodReflection->getName(), ['map', 'mapWithKeys'], true);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $arrayArg    = $methodCall->getArg('array', 0);
        $callbackArg = $methodCall->getArg('callback', 1);

        if ($arrayArg === null || $callbackArg === null) {
            return null;
        }

        $array  = $scope->getType($arrayArg->value);
        $key    = $array->getIterableKeyType();
        $value  = $array->getIterableValueType();
        $mapped = $this->columnHelper->returnTypeFromCallable(
            $callbackArg->value,
            [$value, $key],
            $scope,
        );

        if ($mapped === null) {
            return null;
        }

        if ($methodReflection->getName() === 'mapWithKeys') {
            return new ArrayType($mapped->getIterableKeyType(), $mapped->getIterableValueType());
        }

        $result = new ArrayType($key, $mapped);

        return $array->isList()->yes()
            ? TypeCombinator::intersect($result, new AccessoryArrayListType())
            : $result;
    }
}
