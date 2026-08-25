<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Database\Eloquent\Builder;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function in_array;

final class EloquentValueExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function getClass(): string
    {
        return Builder::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['soleValue', 'value', 'valueOrFail'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $columnArg = $methodCall->getArg('column', 0);

        if ($columnArg === null) {
            return null;
        }

        $modelType = $scope->getType($methodCall->var)->getTemplateType(Builder::class, 'TModel');
        $type      = $this->columnHelper->getTypeFromArg($modelType, $columnArg, $scope) ?? new MixedType();

        // value() returns null when no row matches; the other methods throw instead.
        return $methodReflection->getName() === 'value'
            ? TypeCombinator::addNull($type)
            : $type;
    }
}
