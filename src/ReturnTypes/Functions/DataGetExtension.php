<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Functions;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;

final class DataGetExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'data_get';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type|null
    {
        $targetArg = $functionCall->getArg('target', 0);
        $keyArg    = $functionCall->getArg('key', 1);

        if ($targetArg === null || $keyArg === null) {
            return null;
        }

        return $this->columnHelper->dataGet(
            $scope->getType($targetArg->value),
            $scope->getType($keyArg->value),
            $functionCall->getArg('default', 2),
            $scope,
        );
    }
}
