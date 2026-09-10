<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Parameters;

use CalebDW\PhpstanLaravel\Support\OrWhereClosureHelper;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\StaticMethodParameterClosureTypeExtension;
use PHPStan\Type\Type;

final class ModelWhereParameterExtension implements StaticMethodParameterClosureTypeExtension
{
    public function __construct(private OrWhereClosureHelper $orWhereClosureHelper)
    {
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return $this->orWhereClosureHelper->isMethodSupported($methodReflection, $parameter);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, ParameterReflection $parameter, Scope $scope): Type|null
    {
        return $this->orWhereClosureHelper->getTypeFromMethodCall($methodCall, $parameter);
    }
}
