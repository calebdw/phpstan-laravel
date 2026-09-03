<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\AppMakeHelper;
use Illuminate\Container\Container;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

/** Array access is only declared on the concrete container, which the application extends. */
final class ContainerArrayAccessDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private AppMakeHelper $appMakeHelper)
    {
    }

    public function getClass(): string
    {
        return Container::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'offsetGet';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        return $this->appMakeHelper->resolveType($methodCall->getArg('key', 0), $scope);
    }
}
