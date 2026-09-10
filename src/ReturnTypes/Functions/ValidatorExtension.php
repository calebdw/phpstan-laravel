<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Functions;

use CalebDW\PhpstanLaravel\Support\ValidationHelper;
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
    public function __construct(
        private ValidationHelper $validationHelper,
        private bool $strictContracts,
    ) {
    }

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'validator';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        if ($functionCall->getArgs() === []) {
            return new ObjectType(Factory::class);
        }

        $class = $this->strictContracts ? ValidatorContract::class : Validator::class;
        $shape = $this->validationHelper->shapeFromRulesArg($functionCall, $scope);

        return $shape === null
            ? new ObjectType($class)
            : $this->validationHelper->validator($shape, ! $this->strictContracts);
    }
}
