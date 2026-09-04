<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

use function array_map;

final class EnumerableToArrayExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'toArray';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $calledOnType = $scope->getType($methodCall->var);

        return new ArrayType(
            $calledOnType->getIterableKeyType(),
            $this->arrayableToArray($calledOnType->getTemplateType(Enumerable::class, 'TValue')),
        );
    }

    /**
     * Collection::toArray() maps Arrayable items through their own toArray().
     * Nested enumerables are Arrayable, so they recurse; a plain array does not.
     */
    private function arrayableToArray(Type $type): Type
    {
        if ($type instanceof UnionType) {
            return TypeCombinator::union(...array_map($this->arrayableToArray(...), $type->getTypes()));
        }

        if ((new ObjectType(Enumerable::class))->isSuperTypeOf($type)->yes()) {
            return new ArrayType(
                $type->getIterableKeyType(),
                $this->arrayableToArray($type->getTemplateType(Enumerable::class, 'TValue')),
            );
        }

        if ((new ObjectType(Arrayable::class))->isSuperTypeOf($type)->yes() && $type->hasMethod('toArray')->yes()) {
            return $type->getMethod('toArray', new OutOfClassScope())->getVariants()[0]->getReturnType();
        }

        return $type;
    }
}
