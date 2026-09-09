<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeUtils;

use function in_array;

final class RelationClosureHelper
{
    private const array CALLBACK = [
        'with'                 => 1,
        'withWhereHas'         => 1,
        'hasMorph'             => 1,
        'doesntHaveMorph'      => 1,
        'whereHasMorph'        => 1,
        'orWhereHasMorph'      => 1,
        'whereDoesntHaveMorph' => 1,
        'orWhereDoesntHaveMorph' => 1,
    ];

    private const array COLUMN = [
        'withWhereRelation'                => 1,
        'whereMorphRelation'               => 1,
        'orWhereMorphRelation'             => 1,
        'whereMorphDoesntHaveRelation'     => 1,
        'orWhereMorphDoesntHaveRelation'   => 1,
    ];

    public function __construct(private BuilderHelper $builderHelper)
    {
    }

    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        if (! $methodReflection->getDeclaringClass()->is(EloquentBuilder::class)) {
            return false;
        }

        $method = $methodReflection->getName();

        return match ($parameter->getName()) {
            'callback' => isset(self::CALLBACK[$method]),
            'column'   => isset(self::COLUMN[$method]),
            default    => false,
        };
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall|StaticCall $methodCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): Type|null {
        $arguments = [];

        foreach ($methodCall->getArgs() as $position => $argument) {
            $arguments[$argument->name?->toString() ?? $position] = $argument->value;
        }

        $method   = $methodReflection->getName();
        $relation = $arguments[$method === 'with' ? 'relations' : 'relation'] ?? $arguments[0] ?? null;
        $types    = $arguments['types'] ?? $arguments[1] ?? null;
        $model    = $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap()->getType('TModel');

        if ($relation === null || $model === null) {
            return null;
        }

        if (in_array($method, ['with', 'withWhereHas', 'withWhereRelation'], true)) {
            $queryType = $this->eagerCallbackType($model, $scope->getType($relation), $method !== 'with');

            if ($queryType === null) {
                return null;
            }
        } else {
            if ($types === null) {
                return null;
            }

            $fallback = [];

            foreach (TypeUtils::flattenTypes($scope->getType($relation)) as $relationType) {
                $fallback[] = $relationType->isString()->yes()
                    ? $this->builderHelper->determineBuilderType($model, $relationType)
                    : $this->builderHelper->determineBuilderType($relationType->getTemplateType(Relation::class, 'TRelatedModel'));
            }

            $fallback = TypeCombinator::union(...$fallback);
            $builders = [];

            foreach (TypeUtils::flattenTypes($scope->getType($types)) as $type) {
                if ($type->isArray()->yes()) {
                    $type = $type->getIterableValueType();
                }

                foreach (TypeUtils::flattenTypes($type) as $target) {
                    $objectType = $target->getClassStringObjectType();
                    $builders[] = $target->isClassString()->yes() && (new ObjectType(Model::class))->isSuperTypeOf($objectType)->yes()
                        ? $this->builderHelper->determineBuilderType($objectType)
                        : $fallback;
                }
            }

            $queryType = TypeCombinator::union(...$builders);
        }

        return TypeTraverser::map($parameter->getType(), static function (Type $type, callable $traverse) use ($queryType): Type {
            if ($type instanceof ClosureType) {
                return $type->traverse(static fn (Type $parameterType): Type => TypeCombinator::union(new ObjectType(EloquentBuilder::class), new ObjectType(Relation::class))->isSuperTypeOf($parameterType)->yes()
                    ? $queryType
                    : $parameterType);
            }

            return $traverse($type);
        });
    }

    private function eagerCallbackType(Type $modelType, Type $relationNames, bool $includeBuilder): Type|null
    {
        $relationType = $relationNames->isConstantScalarValue()->yes()
            ? $this->builderHelper->relationType($modelType, $relationNames)
            : $this->relationObjectType($relationNames);

        if ($relationType === null) {
            return null;
        }

        $relationType = TypeTraverser::map(
            $relationType,
            static fn (Type $type, callable $traverse): Type => $type instanceof StaticType ? $type->getStaticObjectType() : $traverse($type),
        );

        $builderType = $relationNames->isConstantScalarValue()->yes()
            ? $this->builderHelper->determineBuilderType($modelType, $relationNames)
            : $this->builderHelper->determineBuilderType($relationType->getTemplateType(Relation::class, 'TRelatedModel'));

        return $includeBuilder ? TypeCombinator::union($builderType, $relationType) : $relationType;
    }

    private function relationObjectType(Type $type): Type|null
    {
        $relations = [];

        foreach (TypeUtils::flattenTypes($type) as $member) {
            if (! (new ObjectType(Relation::class))->isSuperTypeOf($member)->yes()) {
                continue;
            }

            $relations[] = $member;
        }

        return $relations === [] ? null : TypeCombinator::union(...$relations);
    }
}
