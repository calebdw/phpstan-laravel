<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function in_array;

final class EloquentCollectionMapExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private CollectionHelper $collectionHelper)
    {
    }

    public function getClass(): string
    {
        return EloquentCollection::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['map', 'mapWithKeys'], true)
            && $methodReflection->getDeclaringClass()->getName() === EloquentCollection::class;
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        if ($methodCall->getArgs() === []) {
            return null;
        }

        $receiver = TypeCombinator::intersect($scope->getType($methodCall->var), new ObjectType(EloquentCollection::class));
        $base     = $this->collectionHelper->generic(
            Collection::class,
            $receiver->getTemplateType(Collection::class, 'TKey'),
            $receiver->getTemplateType(Collection::class, 'TValue'),
        );
        $method   = $base->getMethod($methodReflection->getName(), $scope);
        $mapped   = ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $methodCall->getArgs(),
            $method->getVariants(),
            $method->getNamedArgumentsVariants(),
        )->getReturnType();
        $values   = $mapped->getTemplateType(Collection::class, 'TValue');

        return (new ObjectType(Model::class))->isSuperTypeOf($values)->maybe() ? $mapped : null;
    }
}
