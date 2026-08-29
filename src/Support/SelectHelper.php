<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use PHPStan\Analyser\Scope;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class SelectHelper
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    /**
     * The type of an array once select() has rebuilt each of its items from
     * the given keys, leaving the keys of the array itself alone.
     */
    public function selectItems(Type $arrayType, Type $keysType, Scope $scope): Type|null
    {
        if (! $arrayType->isArray()->yes()) {
            return null;
        }

        $constantArrays = $arrayType->getConstantArrays();

        if ($constantArrays === []) {
            $selectedType = $this->selectKeys($arrayType->getIterableValueType(), $keysType, $scope);

            if ($selectedType === null) {
                return null;
            }

            $newType = new ArrayType($arrayType->getIterableKeyType(), $selectedType);

            return $arrayType->isList()->yes()
                ? TypeCombinator::intersect($newType, new AccessoryArrayListType())
                : $newType;
        }

        $types = [];

        foreach ($constantArrays as $constantArray) {
            $builder = ConstantArrayTypeBuilder::createEmpty();

            foreach ($constantArray->getKeyTypes() as $i => $keyType) {
                $selectedType = $this->selectKeys($constantArray->getValueTypes()[$i], $keysType, $scope);

                if ($selectedType === null) {
                    return null;
                }

                $builder->setOffsetValueType($keyType, $selectedType, $constantArray->isOptionalKey($i));
            }

            $types[] = $builder->getArray();
        }

        return TypeCombinator::union(...$types);
    }

    /**
     * The type of an item once select() has rebuilt it from the given keys.
     *
     * The keys are either an array of them or a single one; callers unwrap
     * anything their own method accepts on top of that. Null is returned when
     * the items or the keys say too little to narrow anything, leaving the
     * declared return type in place.
     */
    public function selectKeys(Type $itemType, Type $keysType, Scope $scope): Type|null
    {
        if (! $keysType->isArray()->yes() && ! $keysType->isString()->yes() && ! $keysType->isInteger()->yes()) {
            return null;
        }

        $keysType = $keysType->toArray();

        // Only the keys the item has are kept, and keys it may not have stay
        // optional, which is the shape left by intersecting it with the keys.
        if ($itemType->isArray()->yes()) {
            return $itemType->intersectKeyArray($keysType->flipArray());
        }

        // Objects are read through offsetExists()/isset(), so every key is
        // optional: it is skipped for a null property just as it is for one
        // the object turns out not to have at all.
        if ($itemType->isObject()->yes()) {
            return $this->selectProperties($itemType, $keysType, $scope);
        }

        return null;
    }

    private function selectProperties(Type $itemType, Type $keysType, Scope $scope): Type
    {
        $constantArrays = $keysType->getConstantArrays();

        if ($constantArrays === []) {
            return new ArrayType($keysType->getIterableValueType()->toArrayKey(), new MixedType());
        }

        $types = [];

        foreach ($constantArrays as $constantArray) {
            $builder = ConstantArrayTypeBuilder::createEmpty();

            foreach ($constantArray->getValueTypes() as $keyType) {
                foreach ($keyType->getConstantStrings() as $key) {
                    $propertyType = $this->columnHelper->pluckFromType($itemType, [$key->getValue()], $scope);

                    if ($propertyType === null) {
                        continue;
                    }

                    $builder->setOffsetValueType(
                        new ConstantStringType($key->getValue()),
                        TypeCombinator::removeNull($propertyType),
                        optional: true,
                    );
                }
            }

            $types[] = $builder->getArray();
        }

        return TypeCombinator::union(...$types);
    }
}
