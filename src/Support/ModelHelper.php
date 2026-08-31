<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Model;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Throwable;

use function is_string;

final class ModelHelper
{
    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    /** @param ClassReflection|class-string<Model> $model */
    public function getModelInstance(ClassReflection|string $model): Model|null
    {
        if (is_string($model)) {
            if (! $this->reflectionProvider->hasClass($model)) {
                return null;
            }

            $model = $this->reflectionProvider->getClass($model);
        }

        if (! $model->is(Model::class) || $model->isAbstract()) {
            return null;
        }

        try {
            /** @var Model $modelInstance */
            $modelInstance = $model->getNativeReflection()->newInstance();
        } catch (Throwable) {
            /** @var Model $modelInstance */
            $modelInstance = $model->getNativeReflection()->newInstanceWithoutConstructor();
        }

        return $modelInstance;
    }

    public function getModelKeyType(Type $modelType): Type
    {
        $types       = [];
        $defaultType = new BenevolentUnionType([new IntegerType(), new StringType()]);

        foreach ($modelType->getObjectClassReflections() as $classReflection) {
            $model = $this->getModelInstance($classReflection);

            if ($model === null) {
                continue;
            }

            $types[] = match ($model->getKeyType()) {
                'int', 'integer' => new IntegerType(),
                'string' => new StringType(),
                default => $defaultType,
            };
        }

        return $types === [] ? $defaultType : TypeCombinator::union(...$types);
    }
}
