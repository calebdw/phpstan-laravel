<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\ModelHelper;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class ModelGetKeyExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private ModelHelper $modelHelper)
    {
    }

    public function getClass(): string
    {
        return Model::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getKey';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        return TypeCombinator::addNull(
            $this->modelHelper->getModelKeyType($scope->getType($methodCall->var)),
        );
    }
}
