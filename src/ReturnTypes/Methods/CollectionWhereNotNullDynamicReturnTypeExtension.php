<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\BooleanType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function count;

class CollectionWhereNotNullDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private CollectionHelper $collectionHelper)
    {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'whereNotNull';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);

        if ($calledOnType->getObjectClassNames() === []) {
            return null;
        }

        $keyType   = $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap()->getType('TKey');
        $valueType = $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap()->getType('TValue') ??
            $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap()->getType('TModel');

        if ($keyType === null || $valueType === null) {
            return null;
        }

        $nonFalseyTypes = TypeCombinator::removeNull($valueType);

        if (! $this->argumentIsString($methodCall, $scope)) {
            return $this->collectionHelper->of($calledOnType, $keyType, $nonFalseyTypes);
        }

        $scalarTypes = TypeCombinator::union(
            new StringType(),
            new IntegerType(),
            new FloatType(),
            new BooleanType(),
        );

        $nonFalseyTypes = TypeCombinator::remove($nonFalseyTypes, $scalarTypes);

        return $this->collectionHelper->of($calledOnType, $keyType, $nonFalseyTypes);
    }

    public function argumentIsString(MethodCall $methodCall, Scope $scope): bool
    {
        if (count($methodCall->getArgs()) === 0) {
            return false;
        }

        $argType = $scope->getType($methodCall->getArgs()[0]->value);

        return $argType->isNull()->no();
    }
}
