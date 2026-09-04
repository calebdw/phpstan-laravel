<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Parameters\EnumerableReduceSpreadParameterExtension;
use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class EnumerableReduceSpreadExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ColumnHelper $columnHelper,
        private EnumerableReduceSpreadParameterExtension $spreadParameters,
    ) {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'reduceSpread';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $callbackArg = $methodCall->getArg('callback', 0);
        $initial     = $this->spreadParameters->initialTypes($methodCall, $scope);

        if ($callbackArg === null || $initial === null) {
            return null;
        }

        $slots      = $this->spreadParameters->slots($methodCall, $scope);
        $initialArr = $this->listFrom($initial);

        if ($slots === null) {
            return $initialArr;
        }

        $returnType = $this->columnHelper->returnTypeFromCallable($callbackArg->value, $slots, $scope);

        if ($returnType === null || ! $returnType->isArray()->yes()) {
            return $initialArr;
        }

        // Empty collections return $initial without calling the reducer.
        return TypeCombinator::union($initialArr, $returnType);
    }

    /** @param list<Type> $types */
    private function listFrom(array $types): Type
    {
        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($types as $i => $type) {
            $builder->setOffsetValueType(new ConstantIntegerType($i), $type);
        }

        return TypeCombinator::intersect($builder->getArray(), new AccessoryArrayListType());
    }
}
