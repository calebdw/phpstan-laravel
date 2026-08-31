<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Helpers;

use CalebDW\PhpstanLaravel\Concerns;
use CalebDW\PhpstanLaravel\Support\AuthHelper;
use Illuminate\Contracts\Auth\Factory;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class AuthExtension implements DynamicFunctionReturnTypeExtension
{
    use Concerns\HasContainer;

    public function __construct(private AuthHelper $authHelper)
    {
    }

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'auth';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        if ($functionCall->getArgs() === []) {
            /** @var ?object $class */
            $class = $this->resolve(Factory::class);

            return $class === null ? new ObjectType(Factory::class) : new ObjectType($class::class);
        }

        return $this->authHelper->getGuardTypeFromArg($functionCall->getArg('guard', 0), $scope);
    }
}
