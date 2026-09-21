<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ErrorType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;

use function array_key_exists;
use function class_exists;
use function count;

final class FactoryHelper
{
    /** @var array<class-string, ClassReflection|null> */
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

                $factoryReflection = $this->factoryReflection($modelReflection);
                $types[]           = $factoryReflection === null
                    ? new ErrorType()
                    : $factoryType($factoryReflection);
            }
        }

        return $types === [] ? new NeverType() : TypeCombinator::union(...$types);
    }

    private function factoryReflection(ClassReflection $modelReflection): ClassReflection|null
    {
        $modelClass = $modelReflection->getName();

        if (array_key_exists($modelClass, $this->factories)) {
            return $this->factories[$modelClass];
        }

        return $this->factories[$modelClass] = $this->resolveFactoryReflection($modelReflection);
    }

    private function resolveFactoryReflection(ClassReflection $modelReflection): ClassReflection|null
    {
        if ($modelReflection->hasMethod('newFactory')) {
            $factoryReflections = $modelReflection->getMethod('newFactory', new OutOfClassScope())
                ->getVariants()[0]
                ->getReturnType()
                ->getObjectClassReflections();

            if (count($factoryReflections) === 1) {
                $factoryReflection = $factoryReflections[0];

                if ($factoryReflection->is(Factory::class) && ! $factoryReflection->isAbstract()) {
                    return $factoryReflection;
                }
            }
        }

        $factoryClass = $this->reflectionHelper->classStringPropertyDefault($modelReflection, 'factory');

        if ($factoryClass !== null && $this->reflectionProvider->hasClass($factoryClass)) {
            $factoryReflection = $this->reflectionProvider->getClass($factoryClass);

            if ($factoryReflection->is(Factory::class) && ! $factoryReflection->isAbstract()) {
                return $factoryReflection;
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
                return $factoryReflection;
            }
        }

        /** @phpstan-ignore argument.type (guaranteed to be model class-string) */
        $factoryClass = Factory::resolveFactoryName($modelReflection->getName());

        return class_exists($factoryClass) ? $this->reflectionProvider->getClass($factoryClass) : null;
    }
}
