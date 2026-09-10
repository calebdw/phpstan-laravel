<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Parameters;

use CalebDW\PhpstanLaravel\Support\OrWhereClosureHelper;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\MethodParameterClosureTypeExtension;
use PHPStan\Type\Type;

final class EloquentBuilderWhereParameterExtension implements MethodParameterClosureTypeExtension
{
    public function __construct(private OrWhereClosureHelper $orWhereClosureHelper)
    {
    }

    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return $this->orWhereClosureHelper->isMethodSupported($methodReflection, $parameter);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, ParameterReflection $parameter, Scope $scope): Type|null
    {
        return $this->orWhereClosureHelper->getTypeFromMethodCall($methodCall, $parameter);
    }
}
