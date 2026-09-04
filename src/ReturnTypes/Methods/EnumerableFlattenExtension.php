<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

use function is_numeric;

use const INF;

final class EnumerableFlattenExtension implements DynamicMethodReturnTypeExtension
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
        return $methodReflection->getName() === 'flatten';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);
        $class        = $calledOnType->getObjectClassNames()[0] ?? null;

        if ($class === null) {
            return null;
        }

        $depthArg = $methodCall->getArg('depth', 0);
        $depth    = INF;

        if ($depthArg !== null) {
            $values = $scope->getType($depthArg->value)->getConstantScalarValues();

            if ($values === [] || ! is_numeric($values[0])) {
                return null;
            }

            $depth = (float) $values[0];
        }

        $valueType = $this->flattenValue(
            $calledOnType->getTemplateType(Enumerable::class, 'TValue'),
            $depth,
        );

        if ($valueType instanceof MixedType) {
            return null;
        }

        return $this->collectionHelper->toBase($calledOnType, new IntegerType(), $valueType);
    }

    private function flattenValue(Type $type, float $depth): Type
    {
        if ($depth <= 0) {
            return $type;
        }

        $parts = [];

        foreach ($this->members($type) as $member) {
            if (! $this->isNested($member)) {
                $parts[] = $member;

                continue;
            }

            $inner = $member->getIterableValueType();

            $parts[] = $depth === 1.0
                ? $inner
                : $this->flattenValue($inner, $depth === INF ? INF : $depth - 1);
        }

        return TypeCombinator::union(...$parts);
    }

    /** @return list<Type> */
    private function members(Type $type): array
    {
        return $type instanceof UnionType ? $type->getTypes() : [$type];
    }

    private function isNested(Type $type): bool
    {
        return $type->isArray()->yes()
            || (new ObjectType(Enumerable::class))->isSuperTypeOf($type)->yes();
    }
}
