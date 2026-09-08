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
use PHPStan\Type\Type;

use function in_array;

final class EnumerableDuplicatesExtension implements DynamicMethodReturnTypeExtension
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
        return in_array($methodReflection->getName(), ['duplicates', 'duplicatesStrict'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);

        if ($calledOnType->getObjectClassNames() === []) {
            return null;
        }

        $keyType   = $calledOnType->getTemplateType(Enumerable::class, 'TKey');
        $valueType = $calledOnType->getTemplateType(Enumerable::class, 'TValue');
        $callback  = $methodCall->getArg('callback', 0);

        if ($callback !== null) {
            $mapped = $this->columnHelper->getTypeFromArg($valueType, $callback, $scope);

            if ($mapped !== null) {
                $valueType = $mapped;
            }
        }

        return $this->collectionHelper->of($calledOnType, $keyType, $valueType);
    }
}
