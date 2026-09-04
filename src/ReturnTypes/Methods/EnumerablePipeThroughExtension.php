<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class EnumerablePipeThroughExtension implements DynamicMethodReturnTypeExtension
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
        return $methodReflection->getName() === 'pipeThrough';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $callbacksArg = $methodCall->getArg('callbacks', 0)
            ?? $methodCall->getArg('pipes', 0);

        if ($callbacksArg === null || ! $callbacksArg->value instanceof Array_) {
            return null;
        }

        $carry = $scope->getType($methodCall->var);

        foreach ($callbacksArg->value->items as $item) {
            if ($item->unpack) {
                return null;
            }

            $next = $this->columnHelper->getTypeFromArg($carry, new Arg($item->value), $scope);

            if ($next === null) {
                return null;
            }

            $carry = $next;
        }

        return $carry;
    }
}
