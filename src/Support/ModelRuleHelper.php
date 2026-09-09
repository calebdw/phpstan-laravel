<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Model;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function count;

final class ModelRuleHelper
{
    private ObjectType $modelType;

    public function __construct(private BuilderHelper $builderHelper)
    {
        $this->modelType = new ObjectType(Model::class);
    }

    /** @return list<ClassReflection> */
    public function findModelReflectionsFromType(Type $type): array
    {
        $type = TypeCombinator::removeNull($type);

        // Builders and relations carry their model as a template argument;
        // anything else is only interesting when it is a model itself.
        $modelType = $this->modelType->isSuperTypeOf($type)->yes()
            ? $type
            : $this->builderHelper->getModelType($type);

        if ($modelType === null) {
            return [];
        }

        $models = [];

        foreach (TypeCombinator::removeNull($modelType)->getObjectClassReflections() as $class) {
            if ($class->getName() === Model::class || $class->isAbstract() || ! $class->is(Model::class)) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }

    public function findModelReflectionFromType(Type $type): ClassReflection|null
    {
        $models = $this->findModelReflectionsFromType($type);

        return count($models) === 1 ? $models[0] : null;
    }
}
