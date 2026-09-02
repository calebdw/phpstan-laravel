<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Functions;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

final class ValueExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'value';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type|null
    {
        $value = $functionCall->getArg('value', 0)?->value;

        if ($value === null) {
            return null;
        }

        return TypeTraverser::map($scope->getType($value), static function (Type $type, callable $traverse) use ($scope): Type {
            if ($type->isCallable()->yes()) {
                return $type->getCallableParametersAcceptors($scope)[0]->getReturnType();
            }

            return $traverse($type);
        });
    }
}
