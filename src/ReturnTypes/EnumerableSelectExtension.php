<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\SelectHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function count;

final class EnumerableSelectExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private SelectHelper $selectHelper)
    {
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
        $classNames = $scope->getType($methodCall->var)->getObjectClassNames();
        $keysArg    = $methodCall->getArg('keys', 0);

        if (count($classNames) !== 1 || $keysArg === null) {
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

        return new GenericObjectType($classNames[0], [$keyType, $selectedType]);
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
