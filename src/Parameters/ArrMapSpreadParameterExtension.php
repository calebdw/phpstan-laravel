<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Parameters;

use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StaticMethodParameterClosureTypeExtension;
use PHPStan\Type\Type;

use function array_map;

final class ArrMapSpreadParameterExtension implements StaticMethodParameterClosureTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return $methodReflection->getDeclaringClass()->getName() === Arr::class
            && $methodReflection->getName() === 'mapSpread'
            && $parameter->getName() === 'callback';
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, ParameterReflection $parameter, Scope $scope): Type|null
    {
        $arrayArg = $methodCall->getArg('array', 0);

        if ($arrayArg === null) {
            return null;
        }

        $array = $scope->getType($arrayArg->value);
        $slots = $this->columnHelper->spreadSlots(
            $array->getIterableValueType(),
            $array->getIterableKeyType(),
        );

        if ($slots === null) {
            return null;
        }

        return new ClosureType(array_map(static fn ($t) => new SimpleParameterReflection('item', $t), $slots), new MixedType());
    }
}
