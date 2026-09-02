<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Functions;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Validator;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class ValidatorExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(private bool $strictContracts)
    {
    }

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'validator';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        // Runtime behavior depends on argument count, so the nullable $data parameter cannot express this distinction.
        if ($functionCall->getArgs() === []) {
            return new ObjectType(Factory::class);
        }

        return new ObjectType($this->strictContracts ? ValidatorContract::class : Validator::class);
    }
}
