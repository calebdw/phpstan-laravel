<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use CalebDW\PhpstanLaravel\Reflection\ModelPropertyReflection;
use CalebDW\PhpstanLaravel\Schema\ModelSchema;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_map;
use function assert;
use function count;
use function in_array;
use function method_exists;

class ModelPropertyHelper
{
    /** @var array<string, ModelPropertyReflection|false> */
    private array $accessors = [];

    /** @var array<string, ModelPropertyReflection|false> */
    private array $databaseProperties = [];

    public function __construct(
        private TypeStringResolver $stringResolver,
        private ModelSchema $modelSchema,
        private ModelCastHelper $modelCastHelper,
        private ModelHelper $modelHelper,
        private ReflectionHelper $reflectionHelper,
    ) {
    }

    /**
     * Determine if the model has a database property.
     */
    public function hasDatabaseProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        if (! $classReflection->is(Model::class) || $classReflection->isAbstract()) {
            return false;
        }

        $cacheKey = $classReflection->getCacheKey() . '-' . $propertyName;

        return ($this->databaseProperties[$cacheKey] ??= $this->resolveDatabaseProperty($classReflection, $propertyName)) !== false;
    }

    public function getDatabaseProperty(ClassReflection $classReflection, string $propertyName): ModelPropertyReflection
    {
        $property = $this->databaseProperties[$classReflection->getCacheKey() . '-' . $propertyName];
        assert($property !== false);

        return $property;
    }

    private function resolveDatabaseProperty(ClassReflection $classReflection, string $propertyName): ModelPropertyReflection|false
    {
        if ($this->reflectionHelper->hasPropertyTag($classReflection, $propertyName)) {
            return false;
        }

        $modelInstance = $this->modelHelper->getModelInstance($classReflection);

        if ($modelInstance === null) {
            return false;
        }

        if (! $this->modelSchema->hasModelColumn($modelInstance, $propertyName)) {
            // The key is always there, even when no migration declares it.
            if ($propertyName !== $modelInstance->getKeyName()) {
                return false;
            }

            $keyType = $this->stringResolver->resolve($modelInstance->getKeyType());

            return new ModelPropertyReflection($classReflection, $keyType, $keyType);
        }

        $column = $this->modelSchema->getModelColumn($modelInstance, $propertyName);
        $cast   = $this->modelCastHelper->getCastForProperty($classReflection, $propertyName);

        if ($cast !== null) {
            $readableType = $this->modelCastHelper->getReadableType(
                $cast,
                $this->stringResolver->resolve($column->readableType),
            );
            $writableType = $this->modelCastHelper->getWriteableType(
                $cast,
                $this->stringResolver->resolve($column->writeableType),
            );
        } elseif ($this->hasDate($modelInstance, $propertyName)) {
            $readableType = $writableType = $this->modelCastHelper->getDateType();
        } elseif (in_array($column->readableType, ['enum', 'set'], true)) {
            if ($column->options === null || count($column->options) < 1) {
                $readableType = $writableType = new StringType();
            } else {
                $readableType = $writableType = TypeCombinator::union(...array_map(
                    static fn ($option) => new ConstantStringType($option),
                    $column->options,
                ));
            }
        } else {
            $readableType = $this->stringResolver->resolve($column->readableType);
            $writableType = $this->stringResolver->resolve($column->writeableType);
        }

        if ($column->nullable) {
            $readableType = TypeCombinator::addNull($readableType);
            $writableType = TypeCombinator::addNull($writableType);
        }

        return new ModelPropertyReflection($classReflection, $readableType, $writableType);
    }

    /** Determine if the model has a property accessor. */
    public function hasAccessor(ClassReflection $classReflection, string $propertyName): bool
    {
        if (! $classReflection->is(Model::class)) {
            return false;
        }

        $cacheKey = $classReflection->getCacheKey() . '-' . $propertyName;

        return ($this->accessors[$cacheKey] ??= $this->resolveAccessor($classReflection, $propertyName)) !== false;
    }

    public function getAccessor(ClassReflection $classReflection, string $propertyName): ModelPropertyReflection
    {
        $property = $this->accessors[$classReflection->getCacheKey() . '-' . $propertyName];
        assert($property !== false);

        return $property;
    }

    private function resolveAccessor(ClassReflection $classReflection, string $propertyName): ModelPropertyReflection|false
    {
        $camelCase = Str::camel($propertyName);

        // Mirrors getAccessor(): an unusable camel case method is not an
        // answer on its own, as the legacy accessor may still be there.
        if ($classReflection->hasNativeMethod($camelCase)) {
            $methodReflection = $classReflection->getNativeMethod($camelCase);

            if (! $methodReflection->isPublic() && ! $methodReflection->isPrivate()) {
                $returnType = $methodReflection->getVariants()[0]->getReturnType();

                if ((new ObjectType(Attribute::class))->isSuperTypeOf($returnType)->yes()) {
                    return new ModelPropertyReflection(
                        $classReflection,
                        $this->resolveReadableType(
                            $returnType->getTemplateType(Attribute::class, 'TGet'),
                            $classReflection,
                            $propertyName,
                        ),
                        $returnType->getTemplateType(Attribute::class, 'TSet'),
                    );
                }
            }
        }

        $methodName = 'get' . Str::studly($propertyName) . 'Attribute';

        if (! $classReflection->hasNativeMethod($methodName)) {
            return false;
        }

        $returnType = $classReflection->getNativeMethod($methodName)->getVariants()[0]->getReturnType();

        return new ModelPropertyReflection($classReflection, $returnType, $returnType);
    }

    /**
     * A mutator declared with Attribute::set() leaves TGet as never, but
     * Laravel still hands back the underlying attribute on read. never is a
     * subtype of everything, so it silently absorbs any misuse of the value;
     * defer to the column instead.
     */
    private function resolveReadableType(
        Type $readableType,
        ClassReflection $classReflection,
        string $propertyName,
    ): Type {
        if (! $readableType instanceof NeverType) {
            return $readableType;
        }

        if (! $this->hasDatabaseProperty($classReflection, $propertyName)) {
            return new MixedType();
        }

        return $this->getDatabaseProperty($classReflection, $propertyName)->getReadableType();
    }

    private function hasDate(Model $modelInstance, string $propertyName): bool
    {
        $dates = $modelInstance->getDates();

        // In order to support SoftDeletes
        if (method_exists($modelInstance, 'getDeletedAtColumn')) {
            $dates[] = $modelInstance->getDeletedAtColumn();
        }

        return in_array($propertyName, $dates, true);
    }
}
