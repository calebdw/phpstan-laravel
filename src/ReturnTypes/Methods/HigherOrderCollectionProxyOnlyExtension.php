<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\HigherOrderCollectionProxyHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Support\HigherOrderCollectionProxy;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class HigherOrderCollectionProxyOnlyExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ModelOnlyDynamicMethodReturnTypeExtension $modelOnlyExtension,
        private HigherOrderCollectionProxyHelper $higherOrderCollectionProxyHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function getClass(): string
    {
        return HigherOrderCollectionProxy::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'only';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $proxyType      = $scope->getType($methodCall->var);
        $methodType     = $proxyType->getTemplateType(HigherOrderCollectionProxy::class, 'TMethod');
        $valueType      = $proxyType->getTemplateType(HigherOrderCollectionProxy::class, 'TValue');
        $collectionType = $proxyType->getTemplateType(HigherOrderCollectionProxy::class, 'TCollection');

        $methods = $this->typeHelper->constantStrings($methodType);

        if ($methods === [] || $valueType->canCallMethods()->no()) {
            return null;
        }

        return $this->higherOrderCollectionProxyHelper->determineReturnType(
            $methods,
            $valueType,
            $this->modelOnlyExtension->getTypeForModel($methodCall, $valueType, $scope),
            $collectionType->getObjectClassNames(),
            $collectionType->getIterableKeyType(),
        );
    }
}
