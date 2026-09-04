<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function in_array;

final class EnumerableAggregateExtension implements DynamicMethodReturnTypeExtension
{
    private const array METHODS = ['sum', 'min', 'max'];

    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), self::METHODS, true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $callbackArg = $methodCall->getArg('callback', 0)
            ?? $methodCall->getArg('key', 0);

        if ($callbackArg === null) {
            return null;
        }

        $from = $scope->getType($methodCall->var)->getTemplateType(Enumerable::class, 'TValue');
        $type = $this->columnHelper->getTypeFromArg($from, $callbackArg, $scope);

        if ($type === null) {
            return null;
        }

        // min/max skip an empty collection (and null items), so the result is
        // nullable. sum() starts from 0.
        return $methodReflection->getName() === 'sum' ? $type : TypeCombinator::addNull($type);
    }
}
