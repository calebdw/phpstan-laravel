<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function in_array;

final class EnumerableAggregateExtension implements DynamicMethodReturnTypeExtension
{
    private const array METHODS = ['sum', 'min', 'max', 'avg', 'average', 'median', 'mode'];

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
        $from        = $scope->getType($methodCall->var)->getTemplateType(Enumerable::class, 'TValue');
        $type        = $callbackArg === null
            ? $from
            : $this->columnHelper->getTypeFromArg($from, $callbackArg, $scope);

        if ($type === null) {
            return null;
        }

        return match ($methodReflection->getName()) {
            // min/max skip an empty collection (and null items). sum() starts from 0.
            'sum' => $callbackArg === null ? null : $type,
            'min', 'max' => $callbackArg === null ? null : TypeCombinator::addNull($type),
            'avg', 'average' => $this->averageType($type),
            'median' => $this->medianType($type),
            'mode' => $this->modeType($type),
            default => null,
        };
    }

    /**
     * avg() is `$sum / $count`. PHP int division stays int when it divides
     * evenly, otherwise float. Empty (or all-null) collections return null.
     */
    private function averageType(Type $type): Type
    {
        $result = $type->isInteger()->no() && $type->isFloat()->yes()
            ? new FloatType()
            : TypeCombinator::union(new IntegerType(), new FloatType());

        return TypeCombinator::addNull($result);
    }

    /**
     * Odd count returns the middle item. Even count averages the two middles
     * (which may be float). Empty returns null.
     */
    private function medianType(Type $type): Type
    {
        $result = $type->isInteger()->no() && $type->isFloat()->yes()
            ? $type
            : TypeCombinator::union($type, new FloatType());

        return TypeCombinator::addNull($result);
    }

    /**
     * mode() counts values then returns `keys()->all()` of the highest
     * counts, i.e. the values themselves, or null if empty.
     */
    private function modeType(Type $type): Type
    {
        return TypeCombinator::addNull(TypeCombinator::intersect(
            new ArrayType(new IntegerType(), $type),
            new AccessoryArrayListType(),
        ));
    }
}
