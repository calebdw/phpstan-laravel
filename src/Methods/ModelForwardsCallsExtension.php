<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\EloquentBuilderMethodReflection;
use CalebDW\PhpstanLaravel\Reflection\ModelCounterMethodReflection;
use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeWithClassName;

use function array_map;
use function assert;
use function in_array;

final class ModelForwardsCallsExtension implements MethodsClassReflectionExtension
{
    private const array FORWARDED_COUNTER_METHODS = [
        'increment',
        'decrement',
        'incrementQuietly',
        'decrementQuietly',
        'incrementEach',
        'decrementEach',
        'incrementEachQuietly',
        'decrementEachQuietly',
    ];

    /** @var array<string, MethodReflection|false> */
    private array $cache = [];

    private ObjectType $builderType;

    public function __construct(
        private BuilderHelper $builderHelper,
        private EloquentBuilderForwardsCallsExtension $eloquentBuilderForwardsCallsExtension,
    ) {
        $this->builderType = new ObjectType(Builder::class);
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
        if (! $classReflection->is(Model::class)) {
            return false;
        }

        if (in_array($methodName, self::FORWARDED_COUNTER_METHODS, true) && $classReflection->hasNativeMethod($methodName)) {
            return new ModelCounterMethodReflection($classReflection, $methodName);
        }

        $builderType       = $this->builderHelper->getBuilderTypeForModels(new StaticType($classReflection));
        $builderReflection = $builderType->getClassReflection();

        if ($builderReflection === null) {
            return false;
        }

        if ($builderReflection->hasNativeMethod($methodName)) {
            $reflection = $builderReflection->getNativeMethod($methodName);

            $parametersAcceptor = $this->transformStaticParameters($reflection, $builderType);

            $returnType = TypeTraverser::map($parametersAcceptor->getReturnType(), static function (Type $type, callable $traverse) use ($builderType) {
                if ($type instanceof TypeWithClassName && $type->getClassName() === Builder::class) {
                    return $builderType;
                }

                return $traverse($type);
            });

            return new EloquentBuilderMethodReflection(
                $methodName,
                $builderReflection,
                $parametersAcceptor->getParameters(),
                $returnType,
                $parametersAcceptor->isVariadic(),
            );
        }

        if (! $this->eloquentBuilderForwardsCallsExtension->hasMethod($builderReflection, $methodName)) {
            return false;
        }

        $reflection = $this->eloquentBuilderForwardsCallsExtension->getMethod($builderReflection, $methodName);

        if (! $reflection instanceof EloquentBuilderMethodReflection) {
            return $reflection;
        }

        $returnType = $reflection->getVariants()[0]->getReturnType();

        if (! $returnType instanceof ThisType) {
            return $reflection;
        }

        return new EloquentBuilderMethodReflection(
            $reflection->getName(),
            $reflection->getDeclaringClass(),
            $reflection->getVariants()[0]->getParameters(),
            $returnType->getStaticObjectType(),
            $reflection->getVariants()[0]->isVariadic(),
        );
    }

    private function transformStaticParameters(MethodReflection $method, ObjectType $builder): ParametersAcceptor
    {
        $acceptor = $method->getVariants()[0];

        return new FunctionVariant($acceptor->getTemplateTypeMap(), $acceptor->getResolvedTemplateTypeMap(), array_map(function (
            ParameterReflection $parameter,
        ) use ($builder): ParameterReflection {
            return new SimpleParameterReflection(
                $parameter->getName(),
                $this->transformStaticType($parameter->getType(), $builder),
                $parameter->isOptional(),
                $parameter->passedByReference(),
                $parameter->isVariadic(),
                $parameter->getDefaultValue(),
            );
        }, $acceptor->getParameters()), $acceptor->isVariadic(), $this->transformStaticType($acceptor->getReturnType(), $builder));
    }

    private function transformStaticType(Type $type, ObjectType $builder): Type
    {
        return TypeTraverser::map($type, function (Type $type, callable $traverse) use ($builder): Type {
            if ($type instanceof StaticType && $this->builderType->isSuperTypeOf($type)->yes()) {
                return $builder;
            }

            return $traverse($type);
        });
    }
}
