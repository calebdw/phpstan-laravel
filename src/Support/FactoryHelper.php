<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;

use function array_key_exists;
use function class_exists;

final class FactoryHelper
{
    /** @var array<class-string, list<ClassReflection>> */
    private array $factories = [];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private ReflectionHelper $reflectionHelper,
    ) {
    }

    /** @param callable(ClassReflection): Type $factoryType */
    public function determineFactoryType(Type $modelType, callable $factoryType): Type
    {
        $types = [];

        foreach (TypeUtils::flattenTypes($modelType) as $type) {
            foreach ($type->getObjectClassNames() as $className) {
                if (! $this->reflectionProvider->hasClass($className)) {
                    continue;
                }

                $modelReflection = $this->reflectionProvider->getClass($className);

                if (! $modelReflection->is(Model::class)) {
                    continue;
                }

                $factoryReflections = $this->factoryReflections($modelReflection);
                // A model without a factory still has Factory::factoryForModel()
                // behind it, so fall back to the base rather than erroring out.
                if ($factoryReflections === []) {
                    $types[] = new GenericObjectType(Factory::class, [new ObjectType($className)]);
                    continue;
                }

                foreach ($factoryReflections as $factoryReflection) {
                    $types[] = $factoryType($factoryReflection);
                }
            }
        }

        return $types === [] ? new NeverType() : TypeCombinator::union(...$types);
    }

    /** @return list<ClassReflection> */
    private function factoryReflections(ClassReflection $modelReflection): array
    {
        $modelClass = $modelReflection->getName();

        if (array_key_exists($modelClass, $this->factories)) {
            return $this->factories[$modelClass];
        }

        return $this->factories[$modelClass] = $this->resolveFactoryReflections($modelReflection);
    }

    /** @return list<ClassReflection> */
    private function resolveFactoryReflections(ClassReflection $modelReflection): array
    {
        $factories = $this->factoriesFromMethod($modelReflection, 'newFactory');

        if ($factories !== []) {
            return $factories;
        }

        $factoryClass = $this->reflectionHelper->classStringPropertyDefault($modelReflection, 'factory');

        if ($factoryClass !== null && $this->reflectionProvider->hasClass($factoryClass)) {
            $factoryReflection = $this->reflectionProvider->getClass($factoryClass);

            if ($factoryReflection->is(Factory::class) && ! $factoryReflection->isAbstract()) {
                return [$factoryReflection];
            }
        }

        $factoryClass = $this->reflectionHelper->attributeClassName(
            $modelReflection,
            UseFactory::class,
            inherited: false,
        );

        if ($factoryClass !== null && $this->reflectionProvider->hasClass($factoryClass)) {
            $factoryReflection = $this->reflectionProvider->getClass($factoryClass);

            if ($factoryReflection->is(Factory::class) && ! $factoryReflection->isAbstract()) {
                return [$factoryReflection];
            }
        }

        $factories = $this->factoriesFromMethod($modelReflection, 'factory');

        if ($factories !== []) {
            return $factories;
        }

        /** @phpstan-ignore argument.type (guaranteed to be model class-string) */
        $factoryClass = Factory::resolveFactoryName($modelReflection->getName());

        return class_exists($factoryClass) ? [$this->reflectionProvider->getClass($factoryClass)] : [];
    }

    /** @return list<ClassReflection> */
    private function factoriesFromMethod(ClassReflection $modelReflection, string $methodName): array
    {
        if (! $modelReflection->hasNativeMethod($methodName)) {
            return [];
        }

        $factories = [];

        foreach ($modelReflection->getNativeMethod($methodName)->getVariants() as $variant) {
            foreach ($variant->getReturnType()->getObjectClassReflections() as $factory) {
                if (! $factory->is(Factory::class) || $factory->isAbstract()) {
                    continue;
                }

                $factories[] = $factory;
            }
        }

        return $factories;
    }
}
