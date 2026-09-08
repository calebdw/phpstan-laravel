<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function in_array;
use function is_numeric;

use const INF;

final class ArrNestingExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private CollectionHelper $collectionHelper)
    {
    }

    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['collapse', 'flatten', 'dot'], true);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $arrayArg = $methodCall->getArg('array', 0);

        if ($arrayArg === null) {
            return null;
        }

        $array = $scope->getType($arrayArg->value);

        return match ($methodReflection->getName()) {
            'collapse' => $this->collapseType($array),
            'flatten' => $this->flattenType($array, $methodCall, $scope),
            'dot' => $this->dotType($array, $methodCall, $scope),
            default => null,
        };
    }

    private function collapseType(Type $array): Type|null
    {
        $valueType = $array->getIterableValueType();

        if ($valueType->isIterable()->no()) {
            return null;
        }

        $inner = $valueType->getIterableValueType();

        if ($inner instanceof MixedType) {
            return null;
        }

        return $this->listOf($inner);
    }

    private function flattenType(Type $array, StaticCall $methodCall, Scope $scope): Type|null
    {
        $depth = $this->depth($methodCall, $scope, 1);

        if ($depth === null) {
            return null;
        }

        $valueType = $this->collectionHelper->flattenValue($array->getIterableValueType(), $depth);

        if ($valueType instanceof MixedType) {
            return null;
        }

        return $this->listOf($valueType);
    }

    private function dotType(Type $array, StaticCall $methodCall, Scope $scope): Type|null
    {
        if ($array->isArray()->no()) {
            return null;
        }

        $depth = $this->depth($methodCall, $scope, 2);

        if ($depth === null) {
            return null;
        }

        $leafType = $this->collectionHelper->dottedLeaves($array->getIterableValueType(), $depth);

        if ($leafType instanceof MixedType) {
            return null;
        }

        return new ArrayType(new StringType(), $leafType);
    }

    private function depth(StaticCall $methodCall, Scope $scope, int $position): float|null
    {
        $depthArg = $methodCall->getArg('depth', $position);
        $depth    = INF;

        if ($depthArg !== null) {
            $values = $scope->getType($depthArg->value)->getConstantScalarValues();

            if ($values === [] || ! is_numeric($values[0])) {
                return null;
            }

            $depth = (float) $values[0];
        }

        return $depth === INF ? CollectionHelper::MAX_NESTING : $depth;
    }

    private function listOf(Type $value): Type
    {
        return TypeCombinator::intersect(new ArrayType(new IntegerType(), $value), new AccessoryArrayListType());
    }
}
