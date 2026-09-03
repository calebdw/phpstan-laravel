<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Functions;

use CalebDW\PhpstanLaravel\Support\AuthHelper;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use Illuminate\Contracts\Auth\Factory;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;

final class AuthExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(private AuthHelper $authHelper, private ContainerHelper $containerHelper)
    {
    }

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'auth';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        if ($functionCall->getArgs() === []) {
            return $this->containerHelper->getType(Factory::class);
        }

        return $this->authHelper->getGuardTypeFromArg($functionCall->getArg('guard', 0), $scope);
    }
}
