<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

use function in_array;

final class EnumerableCollapseExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['collapse', 'collapseWithKeys'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);
        $class        = $calledOnType->getObjectClassNames()[0] ?? null;

        if ($class === null) {
            return null;
        }

        $valueType = $calledOnType->getTemplateType(Enumerable::class, 'TValue');

        // Arr::collapse only unwraps arrays and Collections; anything else is
        // skipped. isIterable() is the same test without pulling in Arrayable.
        if ($valueType->isIterable()->no()) {
            return null;
        }

        $innerValue = $valueType->getIterableValueType();

        if ($innerValue instanceof MixedType) {
            return null;
        }

        // collapse() uses array_merge, so keys are reindexed. collapseWithKeys()
        // uses array_replace, so the inner keys survive. Both build the result
        // with newInstance(), so the receiver's class is kept.
        $keyType = $methodReflection->getName() === 'collapseWithKeys'
            ? $valueType->getIterableKeyType()
            : new IntegerType();

        return new GenericObjectType($class, [$keyType, $innerValue]);
    }
}
