<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\EloquentBuilderMethodReflection;
use CalebDW\PhpstanLaravel\Reflection\ModelCounterMethodReflection;
use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeWithClassName;

use function array_key_exists;
use function array_map;
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

    /** @var array<string, MethodReflection> */
    private array $cache = [];

    public function __construct(
        private BuilderHelper $builderHelper,
        private EloquentBuilderForwardsCallsExtension $eloquentBuilderForwardsCallsExtension,
    ) {
    }

    /**
     * @throws MissingMethodFromReflectionException
     * @throws ShouldNotHappenException
     */
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (array_key_exists($classReflection->getCacheKey() . '-' . $methodName, $this->cache)) {
            return true;
        }

        $methodReflection = $this->findMethod($classReflection, $methodName);

        if ($methodReflection !== null) {
            $this->cache[$classReflection->getCacheKey() . '-' . $methodName] = $methodReflection;

            return true;
        }

        return false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return $this->cache[$classReflection->getCacheKey() . '-' . $methodName];
    }

    /**
     * @throws ShouldNotHappenException
     * @throws MissingMethodFromReflectionException
     */
    private function findMethod(ClassReflection $classReflection, string $methodName): MethodReflection|null
    {
        if (! $classReflection->is(Model::class)) {
            return null;
        }

        if (in_array($methodName, self::FORWARDED_COUNTER_METHODS, true) && $classReflection->hasNativeMethod($methodName)) {
            return new ModelCounterMethodReflection($classReflection, $methodName);
        }

        $builderType       = $this->builderHelper->getBuilderTypeForModels(new StaticType($classReflection));
        $builderReflection = $builderType->getClassReflection();

        if ($builderReflection === null) {
            return null;
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
            return null;
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
        return TypeTraverser::map($type, static function (Type $type, callable $traverse) use ($builder): Type {
            if ($type instanceof StaticType && (new ObjectType(Builder::class))->isSuperTypeOf($type)->yes()) {
                return $builder;
            }

            return $traverse($type);
        });
    }
}
