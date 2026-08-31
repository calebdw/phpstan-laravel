<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\AuthHelper;
use Illuminate\Contracts\Auth\Guard;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function in_array;

final class GuardExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private AuthHelper $authHelper)
    {
    }

    public function getClass(): string
    {
        return Guard::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['user', 'authenticate'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        return $this->authHelper->getUserType(
            guards: $this->authHelper->getGuardFromCall($methodCall, $scope),
            nullable: $methodReflection->getName() === 'user',
        );
    }
}
