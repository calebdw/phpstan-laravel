<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

use function is_numeric;

use const INF;

final class EnumerableDotExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private CollectionHelper $collectionHelper)
    {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'dot';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);

        if ($calledOnType->getObjectClassNames() === []) {
            return null;
        }

        $valueType = $calledOnType->getTemplateType(Enumerable::class, 'TValue');

        if ($valueType->isArray()->no()) {
            return null;
        }

        $depthArg = $methodCall->getArg('depth', 0);
        $depth    = INF;

        if ($depthArg !== null) {
            $values = $scope->getType($depthArg->value)->getConstantScalarValues();

            if ($values === [] || ! is_numeric($values[0])) {
                return null;
            }

            $depth = (float) $values[0];
        }

        if ($depth === INF) {
            $depth = CollectionHelper::MAX_NESTING;
        }

        $leafType = $this->collectionHelper->dottedLeaves($valueType, $depth);

        if ($leafType instanceof MixedType) {
            return null;
        }

        return $this->collectionHelper->toBase($calledOnType, new StringType(), $leafType);
    }
}
