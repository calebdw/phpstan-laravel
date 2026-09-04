<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;

final class EnumerableCountByExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ColumnHelper $columnHelper,
        private CollectionHelper $collectionHelper,
    ) {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'countBy';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $calledOnType = $scope->getType($methodCall->var);
        $valueType    = $calledOnType->getTemplateType(Enumerable::class, 'TValue');
        $countByArg   = $methodCall->getArg('countBy', 0);

        // No argument counts by the values themselves, the same way groupBy()
        // with the identity callback would.
        $keyType = $countByArg === null
            ? $this->columnHelper->normalizeGroupKey($valueType)
            : $this->columnHelper->normalizeGroupKey(
                $this->columnHelper->getKeyType($valueType, $countByArg, $scope),
            );

        return $this->collectionHelper->toBase($calledOnType, $keyType, new IntegerType());
    }
}
