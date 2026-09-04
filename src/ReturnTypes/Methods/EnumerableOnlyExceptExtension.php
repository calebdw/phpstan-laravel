<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function in_array;

final class EnumerableOnlyExceptExtension implements DynamicMethodReturnTypeExtension
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
        return in_array($methodReflection->getName(), ['only', 'except'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);

        // Eloquent collections filter by model key and reindex; Support and
        // lazy collections are Arr::only / Arr::except over TKey.
        if ((new ObjectType(EloquentCollection::class))->isSuperTypeOf($calledOnType)->yes()) {
            return null;
        }

        $class = $calledOnType->getObjectClassNames()[0] ?? null;

        if ($class === null) {
            return null;
        }

        $keysArg = $methodCall->getArg('keys', 0);

        if ($keysArg === null) {
            return null;
        }

        $items = new ArrayType(
            $calledOnType->getTemplateType(Enumerable::class, 'TKey'),
            $calledOnType->getTemplateType(Enumerable::class, 'TValue'),
        );

        $keys = $scope->getType($keysArg->value)->toArray()->flipArray();

        if ($methodReflection->getName() === 'only') {
            $result = $items->intersectKeyArray($keys);

            return $this->collectionHelper->generic($class, $result->getIterableKeyType(), $result->getIterableValueType());
        }

        return $this->collectionHelper->generic(
            $class,
            TypeCombinator::remove($items->getIterableKeyType(), $keys->getIterableKeyType()),
            $items->getIterableValueType(),
        );
    }
}
