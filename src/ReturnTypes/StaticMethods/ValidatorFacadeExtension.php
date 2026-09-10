<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\ValidationHelper;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function in_array;

final class ValidatorFacadeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private ValidationHelper $validationHelper)
    {
    }

    public function getClass(): string
    {
        return Validator::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['make', 'validate'], true);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $shape = $this->validationHelper->shapeFromRulesArg($methodCall, $scope);

        if ($methodReflection->getName() === 'validate') {
            return $shape;
        }

        return $shape === null ? null : $this->validationHelper->validator($shape);
    }
}
