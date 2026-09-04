<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Parameters\EnumerableMapSpreadParameterExtension;
use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class EnumerableMapSpreadExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private CollectionHelper $collectionHelper,
        private ColumnHelper $columnHelper,
        private EnumerableMapSpreadParameterExtension $spreadParameters,
    ) {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'mapSpread';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $callbackArg = $methodCall->getArg('callback', 0);

        if ($callbackArg === null) {
            return null;
        }

        $calledOnType = $scope->getType($methodCall->var);
        $keyType      = $calledOnType->getTemplateType(Enumerable::class, 'TKey');
        $slots        = $this->spreadParameters->slots(
            $calledOnType->getTemplateType(Enumerable::class, 'TValue'),
            $keyType,
        );

        if ($slots === null) {
            return null;
        }

        $returnType = $this->columnHelper->returnTypeFromCallable($callbackArg->value, $slots, $scope);

        if ($returnType === null) {
            return null;
        }

        return $this->collectionHelper->of($calledOnType, $keyType, $returnType);
    }
}
