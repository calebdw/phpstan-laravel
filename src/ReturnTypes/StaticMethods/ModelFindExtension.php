<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_filter;
use function count;
use function in_array;

final class ModelFindExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private ReflectionProvider $reflectionProvider, private CollectionHelper $collectionHelper)
    {
    }

    public function getClass(): string
    {
        return Model::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        $methodName = $methodReflection->getName();

        if (! Str::startsWith($methodName, 'find')) {
            return false;
        }

        return $this->reflectionProvider->getClass(Builder::class)->hasNativeMethod($methodName) ||
            $this->reflectionProvider->getClass(QueryBuilder::class)->hasNativeMethod($methodName);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
    {
        if (count($methodCall->getArgs()) < 1) {
            return new ErrorType();
        }

        $class = $methodCall->class;

        if ($class instanceof Name) {
            $modelNames = [$scope->resolveName($class)];
        } else {
            $modelNames = $scope->getType($class)->getObjectTypeOrClassStringObjectType()->getObjectClassNames();

            if ($modelNames === []) {
                return new ErrorType();
            }
        }

        $types = [];

        foreach ($modelNames as $modelName) {
            $returnType = ParametersAcceptorSelector::selectFromArgs($scope, $methodCall->getArgs(), $methodReflection->getVariants(), $methodReflection instanceof ExtendedMethodReflection ? $methodReflection->getNamedArgumentsVariants() : null)->getReturnType();

            if ($methodReflection->getName() === 'findSole') {
                return $returnType;
            }

            $id = $methodCall->getArg('id', 0) ?? $methodCall->getArg('ids', 0);

            if ($id === null) {
                return new ErrorType();
            }

            $argType = $scope->getType($id->value);

            if ($argType instanceof MixedType || $argType->isIterable()->maybe()) {
                $types[] = $this->collectionHelper->replaceCollectionsInType($returnType);
                continue;
            }

            if ($argType->isIterable()->yes()) {
                if (in_array(Collection::class, $returnType->getReferencedClasses(), true)) {
                    $types[] = $this->collectionHelper->determineCollectionType($modelName);
                    continue;
                }

                $types[] = TypeCombinator::remove($returnType, new ObjectType($modelName));
                continue;
            }

            $types[] = TypeCombinator::remove(
                TypeCombinator::remove(
                    $returnType,
                    new ArrayType(new MixedType(), new ObjectType($modelName)),
                ),
                new ObjectType(Collection::class),
            );
        }

        $types = array_filter($types);

        return TypeCombinator::union(...$types);
    }
}
