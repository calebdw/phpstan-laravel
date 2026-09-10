<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ValidationHelper;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function in_array;

final class RequestValidateExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private ValidationHelper $validationHelper)
    {
    }

    public function getClass(): string
    {
        return Request::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['validate', 'validateWithBag'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        return $this->validationHelper->shapeFromRulesArg(
            $methodCall,
            $scope,
            'rules',
            $methodReflection->getName() === 'validateWithBag' ? 1 : 0,
        );
    }
}
