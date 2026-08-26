<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\HigherOrderCollectionProxyHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HigherOrderCollectionProxy;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function count;

#[AutowiredService]
final class HigherOrderCollectionProxyOnlyExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ModelOnlyDynamicMethodReturnTypeExtension $modelOnlyExtension,
        private HigherOrderCollectionProxyHelper $higherOrderCollectionProxyHelper,
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

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        $proxyType      = $scope->getType($methodCall->var);
        $methodType     = $proxyType->getTemplateType(HigherOrderCollectionProxy::class, 'T');
        $valueType      = $proxyType->getTemplateType(HigherOrderCollectionProxy::class, 'TValue');
        $collectionType = $proxyType->getTemplateType(HigherOrderCollectionProxy::class, 'TCollection');

        $methods = $methodType->getConstantStrings();

        if (count($methods) !== 1 || $valueType->canCallMethods()->no()) {
            return null;
        }

        $collectionClassNames = $collectionType->getObjectClassNames();
        $collectionClassName  = count($collectionClassNames) === 0
            ? Collection::class
            : $collectionClassNames[0];

        return $this->higherOrderCollectionProxyHelper->determineReturnType(
            $methods[0]->getValue(),
            $valueType,
            $this->modelOnlyExtension->getTypeForModel($methodCall, $valueType, $scope),
            $collectionClassName,
            $collectionType->getIterableKeyType(),
        );
    }
}
