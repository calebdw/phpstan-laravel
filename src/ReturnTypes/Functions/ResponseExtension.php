<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Functions;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class ResponseExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'response';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        // Runtime behavior depends on argument count, so the nullable $content parameter cannot express this distinction.
        if ($functionCall->getArgs() === []) {
            return new ObjectType(ResponseFactory::class);
        }

        return new ObjectType(Response::class);
    }
}
