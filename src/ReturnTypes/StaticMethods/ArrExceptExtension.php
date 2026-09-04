<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

use function array_shift;
use function explode;
use function is_string;
use function str_contains;

final class ArrExceptExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'except';
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $arrayArg = $methodCall->getArg('array', 0);
        $keysArg  = $methodCall->getArg('keys', 1);

        if ($arrayArg === null || $keysArg === null) {
            return null;
        }

        $arrayType = $scope->getType($arrayArg->value);

        if (! $arrayType->isArray()->yes()) {
            return null;
        }

        $sets = $this->keySets($scope->getType($keysArg->value));

        if ($sets === []) {
            return null;
        }

        $types = [];

        foreach ($sets as $keys) {
            $result = $arrayType;

            foreach ($keys as $key) {
                $result = $this->forgetKey($result, $key);
            }

            $types[] = $result;
        }

        return TypeCombinator::union(...$types);
    }

    /** @return list<list<ConstantScalarType>> */
    private function keySets(Type $keys): array
    {
        $sets = [];

        foreach ($keys instanceof UnionType ? $keys->getTypes() : [$keys] as $member) {
            if ($member->isConstantArray()->yes()) {
                foreach ($member->getConstantArrays() as $array) {
                    $set = [];

                    foreach ($array->getValueTypes() as $value) {
                        foreach ($value->getConstantScalarTypes() as $scalar) {
                            if (! $scalar->isInteger()->yes() && ! $scalar->isString()->yes()) {
                                continue;
                            }

                            $set[] = $scalar;
                        }
                    }

                    $sets[] = $set;
                }

                continue;
            }

            foreach ($member->getConstantScalarTypes() as $scalar) {
                if (! $scalar->isInteger()->yes() && ! $scalar->isString()->yes()) {
                    continue;
                }

                $sets[] = [$scalar];
            }
        }

        return $sets;
    }

    private function forgetKey(Type $array, ConstantScalarType $key): Type
    {
        if (! $array->hasOffsetValueType($key)->no()) {
            return $array->unsetOffset($key);
        }

        $values = $key->getConstantScalarValues();
        $value  = $values[0] ?? null;

        if (! is_string($value) || ! str_contains($value, '.')) {
            return $array;
        }

        return $this->forgetPath($array, explode('.', $value));
    }

    /** @param non-empty-list<string> $segments */
    private function forgetPath(Type $array, array $segments): Type
    {
        $key = new ConstantStringType(array_shift($segments));

        if ($segments === []) {
            return $array->unsetOffset($key);
        }

        if ($array->hasOffsetValueType($key)->no()) {
            return $array;
        }

        return $array->setExistingOffsetValueType(
            $key,
            $this->forgetPath($array->getOffsetValueType($key), $segments),
        );
    }
}
