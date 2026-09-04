<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ModelHelper;
use Illuminate\Database\Eloquent\Collection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class EloquentGetDictionaryExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private ModelHelper $modelHelper)
    {
    }

    public function getClass(): string
    {
        return Collection::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getDictionary';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $itemsArg = $methodCall->getArg('items', 0);

        $modelType = $itemsArg === null
            ? $scope->getType($methodCall->var)->getTemplateType(Collection::class, 'TModel')
            : $scope->getType($itemsArg->value)->getIterableValueType();

        return new ArrayType($this->modelHelper->getModelKeyType($modelType), $modelType);
    }
}
