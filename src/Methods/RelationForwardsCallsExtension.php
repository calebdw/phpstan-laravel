<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\EloquentBuilderMethodReflection;
use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;

use function assert;

final class RelationForwardsCallsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    public function __construct(private BuilderHelper $builderHelper, private ReflectionProvider $reflectionProvider)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        $cacheKey = $classReflection->getCacheKey() . '-' . $methodName;

        return ($this->cache[$cacheKey] ??= $this->findMethod($classReflection, $methodName)) !== false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->cache[$classReflection->getCacheKey() . '-' . $methodName];
        assert($method !== false);

        return $method;
    }

    private function findMethod(ClassReflection $classReflection, string $methodName): MethodReflection|false
    {
        if (! $classReflection->is(Relation::class)) {
            return false;
        }

        $relatedModel = $this->builderHelper->getModelType($classReflection);

        if ($relatedModel === null) {
            return false;
        }

        if ($relatedModel->getObjectClassReflections() !== []) {
            $modelReflection = $relatedModel->getObjectClassReflections()[0];
        } else {
            $modelReflection = $this->reflectionProvider->getClass(Model::class);
        }

        if (! $modelReflection->is(Model::class)) {
            return false;
        }

        $builderType = $this->builderHelper->getBuilderTypeForModels($modelReflection->getName());

        if (! $builderType->hasMethod($methodName)->yes()) {
            return false;
        }

        $reflection = $builderType->getMethod($methodName, new OutOfClassScope());

        $parametersAcceptor = $reflection->getVariants()[0];
        $returnType         = $parametersAcceptor->getReturnType();

        if ($this->returnTypeReferencesBuilder($returnType)) {
            $returnType = new ThisType($classReflection);
        }

        return new EloquentBuilderMethodReflection(
            $methodName,
            $reflection->getDeclaringClass(),
            $parametersAcceptor->getParameters(),
            $returnType,
            $parametersAcceptor->isVariadic(),
        );
    }

    /**
     * Checks whether the return type references an Eloquent Builder class.
     *
     * This handles conditional return types (e.g. from Conditionable::when())
     * where isSuperTypeOf() returns maybe() instead of yes() because the
     * type contains unresolved template parameters alongside a Builder branch.
     */
    private function returnTypeReferencesBuilder(Type $returnType): bool
    {
        $builderObjectType = new ObjectType(Builder::class);

        if ($builderObjectType->isSuperTypeOf($returnType)->yes()) {
            return true;
        }

        foreach ($returnType->getReferencedClasses() as $class) {
            if ($builderObjectType->isSuperTypeOf(new ObjectType($class))->yes()) {
                return true;
            }
        }

        return false;
    }
}
