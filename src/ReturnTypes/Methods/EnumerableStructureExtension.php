<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

use function in_array;

final class EnumerableStructureExtension implements DynamicMethodReturnTypeExtension
{
    private const array METHODS = ['concat', 'pad', 'zip'];

    public function __construct(private CollectionHelper $collectionHelper)
    {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), self::METHODS, true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType  = $scope->getType($methodCall->var);
        $calledOnTypes = $calledOnType instanceof UnionType ? $calledOnType->getTypes() : [$calledOnType];
        $results       = [];

        foreach ($calledOnTypes as $receiver) {
            $keyType   = $receiver->getTemplateType(Enumerable::class, 'TKey');
            $valueType = $receiver->getTemplateType(Enumerable::class, 'TValue');

            foreach ($receiver->getObjectClassReflections() as $reflection) {
                $result = match ($methodReflection->getName()) {
                    'concat' => $this->concatType($reflection, $keyType, $valueType, $methodCall, $scope),
                    'pad' => $this->padType($reflection, $keyType, $valueType, $methodCall, $scope),
                    'zip' => $this->zipType($reflection, $valueType, $methodCall, $scope),
                    default => null,
                };

                if ($result === null) {
                    continue;
                }

                $results[] = $result;
            }
        }

        return $results === [] ? null : TypeCombinator::union(...$results);
    }

    private function concatType(ClassReflection $reflection, Type $keyType, Type $valueType, MethodCall $methodCall, Scope $scope): Type|null
    {
        $source = $methodCall->getArg('source', 0);

        if ($source === null) {
            return null;
        }

        $class = $reflection->getName();
        $key   = $reflection->is(LazyCollection::class)
            ? new IntegerType()
            : TypeCombinator::union($keyType, new IntegerType());

        return $this->collectionHelper->generic(
            $class,
            $key,
            TypeCombinator::union(
                $valueType,
                $scope->getType($source->value)->getIterableValueType()->generalize(GeneralizePrecision::lessSpecific()),
            ),
        );
    }

    private function padType(ClassReflection $reflection, Type $keyType, Type $valueType, MethodCall $methodCall, Scope $scope): Type|null
    {
        $value = $methodCall->getArg('value', 1);

        if ($value === null) {
            return null;
        }

        return $this->collectionHelper->generic(
            $reflection->is(EloquentCollection::class) ? Collection::class : $reflection->getName(),
            TypeCombinator::union($keyType, new IntegerType()),
            TypeCombinator::union(
                $valueType,
                $scope->getType($value->value)->generalize(GeneralizePrecision::lessSpecific()),
            ),
        );
    }

    private function zipType(ClassReflection $reflection, Type $valueType, MethodCall $methodCall, Scope $scope): Type
    {
        $values = [$valueType, new NullType()];

        foreach ($methodCall->getArgs() as $arg) {
            $values[] = $scope->getType($arg->value)
                ->getIterableValueType()
                ->generalize(GeneralizePrecision::lessSpecific());
        }

        $class = $reflection->is(EloquentCollection::class) ? Collection::class : $reflection->getName();
        $inner = $this->collectionHelper->generic($class, new IntegerType(), TypeCombinator::union(...$values));

        return $this->collectionHelper->generic($class, new IntegerType(), $inner);
    }
}
