<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\ModelFactoryMethodReflection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\ErrorType;

class ModelFactoryMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, bool> */
    private array $methods = [];

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! $classReflection->is(Factory::class)) {
            return false;
        }

        $cacheKey = $classReflection->getCacheKey() . '-' . $methodName;

        return $this->methods[$cacheKey] ??= $this->findMethod($classReflection, $methodName);
    }

    private function findMethod(ClassReflection $classReflection, string $methodName): bool
    {
        $modelType = $classReflection->getObjectType()->getTemplateType(Factory::class, 'TModel');

        if ($modelType instanceof ErrorType) {
            return false;
        }

        if ($methodName === 'trashed') {
            foreach ($modelType->getObjectClassReflections() as $modelReflection) {
                if ($modelReflection->hasTraitUse(SoftDeletes::class)) {
                    return true;
                }
            }

            return false;
        }

        if (! Str::startsWith($methodName, ['for', 'has'])) {
            return false;
        }

        $relationship = Str::camel(Str::substr($methodName, 3));

        return $modelType->hasMethod($relationship)->yes();
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new ModelFactoryMethodReflection($classReflection, $methodName);
    }
}
