<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Schema\ModelSchema;
use CalebDW\PhpstanLaravel\Support\ModelHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PHPStan\Type\AcceptsResult;
use PHPStan\Type\CompoundType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Generic\TemplateTypeReference;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\IsSuperTypeOfResult;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;

use function count;
use function explode;
use function sprintf;
use function str_contains;

class GenericModelPropertyType extends StringType
{
    public function __construct(
        private Type $type,
        private ModelSchema $modelSchema,
        private ModelHelper $modelHelper,
    ) {
        parent::__construct();
    }

    public function describe(VerbosityLevel $level): string
    {
        return 'model property of ' . $this->type->describe($level);
    }

    /** @return string[] */
    public function getReferencedClasses(): array
    {
        return $this->getGenericType()->getReferencedClasses();
    }

    public function getGenericType(): Type
    {
        return $this->type;
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $this->type->equals($type->type);
    }

    public function accepts(Type $type, bool $strictTypes): AcceptsResult
    {
        if ($type instanceof CompoundType) {
            return $type->isAcceptedBy($this, $strictTypes);
        }

        if (count($type->getConstantStrings()) === 1) {
            $givenString = $type->getConstantStrings()[0]->getValue();
            $genericType = $this->getGenericType();

            if ($genericType instanceof MixedType) {
                return AcceptsResult::createYes();
            }

            if (count($genericType->getObjectClassNames()) < 1) {
                return AcceptsResult::createYes();
            }

            if (str_contains($givenString, ' as ')) {
                $givenString = explode(' as ', $givenString)[0];
            }

            if (str_contains($givenString, '.')) {
                $tableName    = Str::beforeLast($givenString, '.');
                $propertyName = Str::afterLast($givenString, '.');
                /** @var class-string<Model> $modelClass */
                $modelClass = $genericType->getObjectClassNames()[0];

                // Assume the connection is the same as the generic model's connection
                // or fallback to the default connection
                $connection = $this->modelHelper
                    ->getModelInstance($modelClass)
                    ?->getConnectionName()
                    ?? $this->modelSchema->getDefaultConnection();

                if (! isset($this->modelSchema->connections[$connection]->tables[$tableName]->columns[$propertyName])) {
                    return AcceptsResult::createNo([sprintf('Database table "%s" does not have column "%s"', $tableName, $propertyName)]);
                }

                return AcceptsResult::createYes();
            }

            $reasons = [];

            if (! $genericType->hasInstanceProperty($givenString)->yes()) {
                $reasons[] = sprintf('The given string should be a property of %s, %s given.', $this->type->describe(VerbosityLevel::value()), $givenString);
            }

            return new AcceptsResult($genericType->hasInstanceProperty($givenString), $reasons);
        }

        if ($type instanceof self) {
            return new AcceptsResult($this->getGenericType()->accepts($type->getGenericType(), $strictTypes)->result, [sprintf('The given string should be a property of %s', $this->type->describe(VerbosityLevel::value()))]);
        }

        if ($type->isString()->yes()) {
            // It is an arbitrary string, so we cannot check if it is a property of the model.
            // We return yes to not cause issues on levels 7+
            return AcceptsResult::createYes();
        }

        return AcceptsResult::createNo();
    }

    public function isSuperTypeOf(Type $type): IsSuperTypeOfResult
    {
        $constantStrings = $type->getConstantStrings();

        if (count($constantStrings) === 1) {
            if (! $this->getGenericType()->hasInstanceProperty($constantStrings[0]->getValue())->yes()) {
                return IsSuperTypeOfResult::createNo();
            }

            return IsSuperTypeOfResult::createYes();
        }

        if ($type instanceof self) {
            return $this->getGenericType()->isSuperTypeOf($type->getGenericType());
        }

        if ($type instanceof CompoundType) {
            return $type->isSubTypeOf($this);
        }

        return IsSuperTypeOfResult::createNo();
    }

    public function traverse(callable $cb): Type
    {
        $newType = $cb($this->getGenericType());

        if ($newType === $this->getGenericType()) {
            return $this;
        }

        return new self($newType, $this->modelSchema, $this->modelHelper);
    }

    public function inferTemplateTypes(Type $receivedType): TemplateTypeMap
    {
        /** @phpstan-ignore phpstanApi.instanceofType (needs to tell union and intersection members apart) */
        if ($receivedType instanceof UnionType || $receivedType instanceof IntersectionType) {
            return $receivedType->inferTemplateTypesOn($this);
        }

        $constantStrings = $receivedType->getConstantStrings();

        if (count($constantStrings) === 1) {
            $typeToInfer = new ObjectType($constantStrings[0]->getValue());
        } elseif ($receivedType instanceof self) {
            $typeToInfer = $receivedType->type;
        } elseif ($receivedType->isClassString()->yes()) {
            $typeToInfer = $this->getGenericType();

            if ($typeToInfer instanceof TemplateType) {
                $typeToInfer = $typeToInfer->getBound();
            }

            $typeToInfer = TypeCombinator::intersect($typeToInfer, new ObjectWithoutClassType());
        } else {
            return TemplateTypeMap::createEmpty();
        }

        if (! $this->getGenericType()->isSuperTypeOf($typeToInfer)->no()) {
            return $this->getGenericType()->inferTemplateTypes($typeToInfer);
        }

        return TemplateTypeMap::createEmpty();
    }

    /** @return TemplateTypeReference[] */
    public function getReferencedTemplateTypes(TemplateTypeVariance $positionVariance): array
    {
        $variance = $positionVariance->compose(TemplateTypeVariance::createCovariant());

        return $this->getGenericType()->getReferencedTemplateTypes($variance);
    }

    public function hasTemplateOrLateResolvableType(): bool
    {
        return true;
    }

    /** @param  mixed[] $properties */
    public static function __set_state(array $properties): Type
    {
        return new self($properties['type'], $properties['modelSchema'], $properties['modelHelper']);
    }
}
