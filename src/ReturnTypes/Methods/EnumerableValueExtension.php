<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class EnumerableValueExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'value';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $keyArg = $methodCall->getArg('key', 0);

        if ($keyArg === null) {
            return null;
        }

        $from = $scope->getType($methodCall->var)->getTemplateType(Enumerable::class, 'TValue');
        $type = $this->columnHelper->getTypeFromArg($from, $keyArg, $scope);

        if ($type === null) {
            return null;
        }

        $defaultArg = $methodCall->getArg('default', 1);

        if ($defaultArg === null) {
            return TypeCombinator::addNull($type);
        }

        $defaultType = $scope->getType($defaultArg->value);

        if ($defaultType instanceof ClosureType) {
            $defaultType = $defaultType->getReturnType();
        }

        return TypeCombinator::union($type, $defaultType);
    }
}
