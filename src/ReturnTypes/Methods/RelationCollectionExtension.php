<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function count;
use function in_array;
use function is_string;

final class RelationCollectionExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private CollectionHelper $collectionHelper,
        private BuilderHelper $builderHelper,
    ) {
    }

    public function getClass(): string
    {
        return Relation::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $modelType = $this->builderHelper->getModelType($methodReflection->getDeclaringClass());

        if ($modelType === null || $modelType->getObjectClassNames() === []) {
            return false;
        }

        $methodName = $methodReflection->getName();

        return $methodReflection->getDeclaringClass()->hasNativeMethod($methodName) ||
            $this->reflectionProvider->getClass(Builder::class)->hasNativeMethod($methodName) ||
            $this->reflectionProvider->getClass(QueryBuilder::class)->hasNativeMethod($methodName);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $returnType = ParametersAcceptorSelector::selectFromArgs($scope, $methodCall->getArgs(), $methodReflection->getVariants())->getReturnType();

        if (
            in_array($methodReflection->getName(), ['when', 'unless'], true)
            && ! $methodReflection->getDeclaringClass()->is(Relation::class)
            && (new ObjectType(Builder::class))->isSuperTypeOf($returnType)->yes()
        ) {
            $callback = $methodCall->getArg('callback', 1)?->value;
            $default  = $methodCall->getArg('default', 2)?->value;
            $builder  = $methodReflection->getDeclaringClass()->getObjectType();

            if (
                $callback !== null && $this->returnsReceiver($callback, $builder, $scope)
                && ($default === null || $this->returnsReceiver($default, $builder, $scope))
            ) {
                return TypeCombinator::intersect($scope->getType($methodCall->var), new ObjectType(Relation::class));
            }
        }

        if (! in_array(Collection::class, $returnType->getReferencedClasses(), true)) {
            return null;
        }

        return $this->collectionHelper->replaceCollectionsInType($returnType);
    }

    private function returnsReceiver(Expr $callback, Type $builder, Scope $scope): bool
    {
        if (! $callback instanceof ArrowFunction && ! $callback instanceof Closure) {
            return false;
        }

        $parameter = $callback->getParams()[0]->var ?? null;

        if (! $parameter instanceof Variable || ! is_string($parameter->name)) {
            return false;
        }

        if ($callback instanceof ArrowFunction) {
            $value = $callback->expr;
        } else {
            if (count($callback->stmts) !== 1 || ! $callback->stmts[0] instanceof Return_) {
                return false;
            }

            $value = $callback->stmts[0]->expr;
        }

        while ($value instanceof MethodCall) {
            if (! $value->name instanceof Identifier || ! $builder->hasMethod($value->name->toString())->yes()) {
                return false;
            }

            foreach ($builder->getObjectClassReflections() as $class) {
                $method = $class->hasNativeMethod($value->name->toString())
                    ? $class->getNativeMethod($value->name->toString())
                    : $class->getMethod($value->name->toString(), $scope);

                foreach ($method->getVariants() as $variant) {
                    if (! $variant->getReturnType() instanceof ThisType) {
                        return false;
                    }
                }
            }

            $value = $value->var;
        }

        return $value instanceof Variable && $value->name === $parameter->name;
    }
}
