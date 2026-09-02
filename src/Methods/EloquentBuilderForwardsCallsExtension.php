<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\EloquentBuilderMethodReflection;
use CalebDW\PhpstanLaravel\Reflection\MacroMethodReflection;
use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\TemplateObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ThisType;

use function assert;
use function in_array;

final class EloquentBuilderForwardsCallsExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    /** @var list<string> */
    private array $softDeletesMethods = ['withTrashed', 'onlyTrashed', 'withoutTrashed', 'restore', 'createOrRestore', 'restoreOrCreate'];

    public function __construct(
        private BuilderHelper $builderHelper,
        private ReflectionProvider $reflectionProvider,
        private TypeHelper $typeHelper,
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
        if (! $classReflection->is(EloquentBuilder::class)) {
            return false;
        }

        $modelType = $this->builderHelper->getModelType($classReflection);

        if ($modelType === null) {
            return false;
        }

        if ($modelType instanceof TemplateObjectType) {
            $modelType = $modelType->getBound();
        }

        $ref = $this->builderHelper->searchOnEloquentBuilder($classReflection, $methodName, $modelType);

        if ($ref === null) {
            // Special case for `SoftDeletes` trait
            if (
                ! in_array($methodName, $this->softDeletesMethods, true)
                || ! $this->typeHelper->usesTrait($modelType, SoftDeletes::class)
            ) {
                return false;
            }

            $ref = $this->reflectionProvider->getClass(SoftDeletes::class)->getMethod($methodName, new OutOfClassScope());

            if ($methodName === 'restore') {
                $returnType = new IntegerType();
            } elseif ($methodName === 'restoreOrCreate' || $methodName === 'createOrRestore') {
                $returnType = $modelType;
            } else {
                $returnType = new ThisType($classReflection);
            }

            return new EloquentBuilderMethodReflection(
                $methodName,
                $classReflection,
                $ref->getVariants()[0]->getParameters(),
                $returnType,
                $ref->getVariants()[0]->isVariadic(),
            );
        }

        // Macros have their own reflection. And return type, parameters, etc. are already set with the closure.
        if ($ref instanceof MacroMethodReflection) {
            return $ref;
        }

        $parametersAcceptor = $ref->getVariants()[0];
        // A named scope can shadow a passthru method, e.g. `scopeCount()`. Only
        // forward the declared return type when the method genuinely came from
        // the query builder; a scope that happens to share the name still has
        // to return the builder.
        $isPassthru = $ref->getDeclaringClass()->getName() === QueryBuilder::class
            && $this->builderHelper->methodIsBuilderPassthru($methodName);

        $returnType = $isPassthru
            ? $parametersAcceptor->getReturnType()
            : new ThisType($classReflection);

        return new EloquentBuilderMethodReflection(
            $methodName,
            $classReflection,
            $parametersAcceptor->getParameters(),
            $returnType,
            $parametersAcceptor->isVariadic(),
        );
    }
}
