<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Functions;

use CalebDW\PhpstanLaravel\Support\AppMakeHelper;
use Illuminate\Foundation\Application;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class AppExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(
        private AppMakeHelper $appMakeHelper,
    ) {
    }

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'app' || $functionReflection->getName() === 'resolve';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type|null
    {
        if ($functionCall->getArgs() === []) {
            return new ObjectType(Application::class);
        }

        return $this->appMakeHelper->resolveTypeFromCall($functionCall, $scope);
    }
}
