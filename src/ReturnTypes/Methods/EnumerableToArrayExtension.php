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
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

use function array_map;
use function in_array;

final class EnumerableToArrayExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['toArray', 'jsonSerialize'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $calledOnType = $scope->getType($methodCall->var);

        return new ArrayType(
            $calledOnType->getIterableKeyType(),
            $this->itemToArray($calledOnType->getTemplateType(Enumerable::class, 'TValue')),
        );
    }

    /**
     * toArray() and jsonSerialize() both map Arrayable items through toArray().
     * Nested enumerables recurse; a plain array does not.
     */
    private function itemToArray(Type $type, int $depth = 16): Type
    {
        if ($type instanceof UnionType) {
            return TypeCombinator::union(...array_map(
                fn (Type $t): Type => $this->itemToArray($t, $depth),
                $type->getTypes(),
            ));
        }

        if ((new ObjectType(Enumerable::class))->isSuperTypeOf($type)->yes()) {
            if ($depth <= 0) {
                return new ArrayType($type->getIterableKeyType(), new MixedType());
            }

            return new ArrayType(
                $type->getIterableKeyType(),
                $this->itemToArray($type->getTemplateType(Enumerable::class, 'TValue'), $depth - 1),
            );
        }

        if ((new ObjectType(Arrayable::class))->isSuperTypeOf($type)->yes() && $type->hasMethod('toArray')->yes()) {
            return $type->getMethod('toArray', new OutOfClassScope())->getVariants()[0]->getReturnType();
        }

        return $type;
    }
}
