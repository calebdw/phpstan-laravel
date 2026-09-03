<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\AuthHelper;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function in_array;

final class AuthExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private AuthHelper $authHelper)
    {
    }

    public function getClass(): string
    {
        return Auth::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['user', 'authenticate', 'guard'], true);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        if ($methodReflection->getName() === 'guard') {
            return $this->authHelper->getGuardTypeFromArg($methodCall->getArg('name', 0), $scope);
        }

        return $this->authHelper->getUserType(nullable: $methodReflection->getName() === 'user');
    }
}
