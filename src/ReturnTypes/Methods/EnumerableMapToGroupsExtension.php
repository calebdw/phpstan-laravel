<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_map;
use function count;
use function in_array;

final class EnumerableMapToGroupsExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ColumnHelper $columnHelper,
        private CollectionHelper $collectionHelper,
    ) {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['mapToGroups', 'mapToDictionary'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $callbackArg = $methodCall->getArg('callback', 0);

        if ($callbackArg === null) {
            return null;
        }

        $calledOnType = $scope->getType($methodCall->var);
        $from         = $calledOnType->getTemplateType(Enumerable::class, 'TValue');
        $pairType     = $this->columnHelper->getTypeFromArg($from, $callbackArg, $scope);

        if ($pairType === null || $pairType->isArray()->no()) {
            return null;
        }

        $pair = $this->firstPair($pairType);

        if ($pair === null) {
            return null;
        }

        $classes = $calledOnType->getObjectClassNames();

        if ($classes === []) {
            return null;
        }

        $key = $this->columnHelper->normalizeKey($pair['key']);

        return TypeCombinator::union(...array_map(function (string $class) use ($methodReflection, $key, $pair): Type {
            if ($methodReflection->getName() === 'mapToDictionary') {
                return $this->collectionHelper->generic(
                    $class,
                    $key,
                    new ArrayType(new IntegerType(), $pair['value']),
                );
            }

            return $this->collectionHelper->toBase(
                new ObjectType($class),
                $key,
                $this->collectionHelper->generic($class, new IntegerType(), $pair['value']),
            );
        }, $classes));
    }

    /**
     * mapToDictionary() only reads key()/reset() of the returned array, so a
     * pair ['foo' => 1, 'bar' => 2] groups under foo. Constant arrays keep that
     * first pair; anything else falls back to the iterable key and value.
     *
     * @return array{key: Type, value: Type}|null
     */
    private function firstPair(Type $pairType): array|null
    {
        $arrays = $pairType->getConstantArrays();

        if ($arrays === []) {
            return [
                'key' => $pairType->getIterableKeyType(),
                'value' => $pairType->getIterableValueType(),
            ];
        }

        $keys   = [];
        $values = [];

        foreach ($arrays as $array) {
            $keyTypes   = $array->getKeyTypes();
            $valueTypes = $array->getValueTypes();

            if (count($keyTypes) === 0) {
                continue;
            }

            $keys[]   = $keyTypes[0];
            $values[] = $valueTypes[0];
        }

        if ($keys === []) {
            return null;
        }

        return [
            'key' => TypeCombinator::union(...$keys),
            'value' => TypeCombinator::union(...$values),
        ];
    }
}
