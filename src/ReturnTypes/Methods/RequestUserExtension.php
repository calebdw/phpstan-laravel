<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\AuthHelper;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class RequestUserExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private AuthHelper $authHelper)
    {
    }

    public function getClass(): string
    {
        return Request::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'user';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        if ($methodReflection->getDeclaringClass()->getName() !== Request::class) {
            return null;
        }

        return $this->authHelper->getUserType(
            guards: $this->authHelper->getGuardsFromArg($methodCall->getArg('guard', 0), $scope),
            nullable: true,
        );
    }
}
