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
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;

use function assert;

final class RelationForwardsCallsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    public function __construct(
        private BuilderHelper $builderHelper,
    ) {
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

        foreach ($relatedModel->getObjectClassReflections() as $modelReflection) {
            if (! $modelReflection->is(Model::class)) {
                return false;
            }
        }

        $builderType = $this->builderHelper->getBuilderTypeForModels(
            $relatedModel->getObjectClassNames() ?: [Model::class],
        );

        if (! $builderType->hasMethod($methodName)->yes()) {
            return false;
        }

        $reflection = $builderType->getMethod($methodName, new OutOfClassScope());

        $parametersAcceptor = $reflection->getVariants()[0];
        $returnType         = $parametersAcceptor->getReturnType();

        if (! (new ObjectType(Builder::class))->isSuperTypeOf($returnType)->yes()) {
            return $reflection;
        }

        return new EloquentBuilderMethodReflection(
            $methodName,
            $reflection->getDeclaringClass(),
            $parametersAcceptor->getParameters(),
            new ThisType($classReflection),
            $parametersAcceptor->isVariadic(),
        );
    }
}
