<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\AuthHelper;
use Illuminate\Auth\AuthManager;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function in_array;

final class AuthManagerExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private AuthHelper $authHelper)
    {
    }

    public function getClass(): string
    {
        return AuthManager::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['user', 'authenticate', 'guard'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        if ($methodReflection->getName() === 'guard') {
            return $this->authHelper->getGuardTypeFromArg($methodCall->getArg('name', 0), $scope);
        }

        return $this->authHelper->getUserType(nullable: $methodReflection->getName() === 'user');
    }
}
