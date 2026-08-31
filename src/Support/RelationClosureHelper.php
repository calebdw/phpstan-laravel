<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use CalebDW\PhpstanLaravel\Reflection\ClosureQueryParameterReflection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_push;
use function array_shift;
use function collect;
use function count;
use function explode;
use function in_array;

final class RelationClosureHelper
{
    /** @var list<string> */
    private array $methods = [
        'has',
        'doesntHave',
        'whereHas',
        'withWhereHas',
        'orWhereHas',
        'whereDoesntHave',
        'orWhereDoesntHave',
        'whereRelation',
        'orWhereRelation',
    ];

    /** @var list<string> */
    private array $morphMethods = [
        'hasMorph',
        'doesntHaveMorph',
        'whereHasMorph',
        'orWhereHasMorph',
        'whereDoesntHaveMorph',
        'orWhereDoesntHaveMorph',
        'whereMorphRelation',
        'orWhereMorphRelation',
    ];

    public function __construct(
        private BuilderHelper $builderHelper,
    ) {
    }

    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        if (! $methodReflection->getDeclaringClass()->is(EloquentBuilder::class)) {
            return false;
        }

        return in_array($methodReflection->getName(), [...$this->methods, ...$this->morphMethods], strict: true);
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall|StaticCall $methodCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): Type|null {
        $method        = $methodReflection->getName();
        $isMorphMethod = in_array($method, $this->morphMethods, strict: true);
        $models        = [];
        $relations     = [];

        if ($isMorphMethod) {
            $models = $this->getMorphModels($methodCall, $scope);
        } else {
            $relations = $this->getRelationsFromMethodCall($methodCall, $scope);
            $models    = $this->getModelsFromRelations($relations);
        }

        if (count($models) === 0) {
            return null;
        }

        $type = $this->builderHelper->getBuilderTypeForModels($models);

        if ($method === 'withWhereHas') {
            $type = TypeCombinator::union($type, ...$relations);
        }

        return new ClosureType([
            new ClosureQueryParameterReflection('query', $type),
            new ClosureQueryParameterReflection('type', $isMorphMethod ? new StringType() : new NeverType()),
        ], new MixedType());
    }

    /** @return array<int, string> */
    private function getMorphModels(MethodCall|StaticCall $methodCall, Scope $scope): array
    {
        $models = null;

        foreach ($methodCall->args as $i => $arg) {
            if ($arg instanceof VariadicPlaceholder) {
                continue;
            }

            if (($i === 1 && $arg->name === null) || $arg->name?->toString() === 'types') {
                $models = $scope->getType($arg->value);
                break;
            }
        }

        if ($models === null) {
            return [];
        }

        return collect($models->getConstantArrays())
            ->flatMap(static fn (ConstantArrayType $t) => $t->getValueTypes())
            ->flatMap(static fn (Type $t) => $t->getConstantStrings())
            ->merge($models->getConstantStrings())
            ->map(static fn (ConstantStringType $t) => $t->getValue())
            ->map(static fn (string $v) => $v === '*' ? Model::class : $v)
            ->values()
            ->all();
    }

    /**
     * @param array<int, Type> $relations
     *
     * @return array<int, string>
     */
    private function getModelsFromRelations(array $relations): array
    {
        return collect($relations)
            ->flatMap(
                static fn (Type $relation) => $relation
                    ->getTemplateType(Relation::class, 'TRelatedModel')
                    ->getObjectClassNames(),
            )
            ->values()
            ->all();
    }

    /** @return array<int, Type> */
    public function getRelationsFromMethodCall(MethodCall|StaticCall $methodCall, Scope $scope): array
    {
        $relationType = null;

        foreach ($methodCall->args as $arg) {
            if ($arg instanceof VariadicPlaceholder) {
                continue;
            }

            if ($arg->name === null || $arg->name->toString() === 'relation') {
                $relationType = $scope->getType($arg->value);
                break;
            }
        }

        if ($relationType === null) {
            return [];
        }

        if ($methodCall instanceof MethodCall) {
            $callerType     = $scope->getType($methodCall->var);
            $calledOnModels = $callerType->getTemplateType(EloquentBuilder::class, 'TModel')->getObjectClassNames()
                ?: $callerType->getTemplateType(Relation::class, 'TRelatedModel')->getObjectClassNames();
        } else {
            $calledOnModels = $methodCall->class instanceof Name
                ? [$scope->resolveName($methodCall->class)]
                : $scope->getType($methodCall->class)->getReferencedClasses();
        }

        return collect($relationType->getConstantStrings())
            ->map(static fn ($type) => $type->getValue())
            ->flatMap(fn ($relation) => $this->getRelationTypeFromString($calledOnModels, explode('.', $relation), $scope))
            ->merge([$relationType])
            ->filter(static fn ($r) => (new ObjectType(Relation::class))->isSuperTypeOf($r)->yes())
            ->values()
            ->all();
    }

    /**
     * @param list<string> $calledOnModels
     * @param list<string> $relationParts
     *
     * @return list<Type>
     */
    public function getRelationTypeFromString(
        array $calledOnModels,
        array $relationParts,
        Scope $scope,
    ): array {
        $relations = [];

        while ($relationName = array_shift($relationParts)) {
            $relations     = [];
            $relatedModels = [];

            foreach ($calledOnModels as $model) {
                $modelType = new ObjectType($model);

                if (! $modelType->hasMethod($relationName)->yes()) {
                    continue;
                }

                $relationType = $modelType->getMethod($relationName, $scope)->getVariants()[0]->getReturnType();

                if (! (new ObjectType(Relation::class))->isSuperTypeOf($relationType)->yes()) {
                    continue;
                }

                $relations[] = $relationType;

                array_push($relatedModels, ...$relationType->getTemplateType(Relation::class, 'TRelatedModel')->getObjectClassNames());
            }

            $calledOnModels = $relatedModels;
        }

        return $relations;
    }
}
