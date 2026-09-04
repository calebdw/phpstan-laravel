<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use CalebDW\PhpstanLaravel\Support\SelectHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class EnumerableSelectExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private SelectHelper $selectHelper,
        private CollectionHelper $collectionHelper,
    ) {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'select';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);
        $keysArg      = $methodCall->getArg('keys', 0);

        if ($calledOnType->getObjectClassNames() === [] || $keysArg === null) {
            return null;
        }

        $templateTypeMap = $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap();
        $keyType         = $templateTypeMap->getType('TKey');
        $valueType       = $templateTypeMap->getType('TValue');

        if ($keyType === null || $valueType === null) {
            return null;
        }

        $selectedType = $this->selectHelper->selectKeys(
            $valueType,
            $this->keysType($scope->getType($keysArg->value)),
            $scope,
        );

        if ($selectedType === null) {
            return null;
        }

        return $this->collectionHelper->of($calledOnType, $keyType, $selectedType);
    }

    /** select() takes the keys out of an Enumerable, but leaves anything else alone. */
    private function keysType(Type $keysType): Type
    {
        if (! (new ObjectType(Enumerable::class))->isSuperTypeOf($keysType)->yes()) {
            return $keysType;
        }

        return new ArrayType($keysType->getIterableKeyType(), $keysType->getIterableValueType());
    }
}
