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

    public function findModelReflectionFromType(Type $type): ClassReflection|null
    {
        $type = TypeCombinator::removeNull($type);

        // Builders and relations carry their model as a template argument;
        // anything else is only interesting when it is a model itself.
        $modelType = $this->modelType->isSuperTypeOf($type)->yes()
            ? $type
            : $this->builderHelper->getModelType($type);

        if ($modelType === null) {
            return null;
        }

        $classReflections = TypeCombinator::removeNull($modelType)->getObjectClassReflections();

        if (count($classReflections) !== 1) {
            return null;
        }

        $modelReflection = $classReflections[0];

        // A bare Model is the unresolved template bound, not a real model.
        if ($modelReflection->getName() === Model::class || $modelReflection->isAbstract()) {
            return null;
        }

        return $modelReflection;
    }
}
