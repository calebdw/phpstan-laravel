<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class EnumerablePluckExtension implements DynamicMethodReturnTypeExtension
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
        return $methodReflection->getName() === 'pluck';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);
        $valueArg     = $methodCall->getArg('value', 0);
        $keyArg       = $methodCall->getArg('key', 1);

        if ($valueArg === null) {
            return null;
        }

        $from  = $calledOnType->getTemplateType(Enumerable::class, 'TValue');
        $array = $this->columnHelper->getArrayType($from, $valueArg, $keyArg, $scope);

        // Eloquent pluck() toBase()s; Support and lazy keep their class via
        // newInstance(). A union of those walks each class.
        $classes = [];

        foreach ($calledOnType->getObjectClassReflections() as $reflection) {
            $classes[] = $reflection->is(EloquentCollection::class)
                ? Collection::class
                : $reflection->getName();
        }

        return $this->collectionHelper->ofClasses($classes, $array->getKeyType(), $array->getItemType());
    }
}
