<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\TypeHelper;
use CalebDW\PhpstanLaravel\Support\ValidationHelper;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Validation\Factory;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function in_array;

final class ValidationFactoryExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ValidationHelper $validationHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function getClass(): string
    {
        return FactoryContract::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['make', 'validate'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $shape = $this->validationHelper->shapeFromRulesArg($methodCall, $scope);

        if ($methodReflection->getName() === 'validate') {
            return $shape;
        }

        return $shape === null
            ? null
            : $this->validationHelper->validator(
                $shape,
                $this->typeHelper->isCalledOn($scope->getType($methodCall->var), Factory::class),
            );
    }
}
