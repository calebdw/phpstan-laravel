<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;

use function explode;
use function in_array;
use function str_contains;

final class ArrGetPullExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['get', 'pull'], true);
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type|null {
        $arrayArg = $methodCall->getArg('array', 0);
        $keyArg   = $methodCall->getArg('key', 1);

        if ($arrayArg === null || $keyArg === null) {
            return null;
        }

        $arrayType = $scope->getType($arrayArg->value);
        $keyType   = $scope->getType($keyArg->value);
        $default   = $methodCall->getArg('default', 2);
        $default   = $default === null ? new NullType() : $this->resolveValueType($scope->getType($default->value), $scope);

        if ($keyType->isNull()->yes()) {
            return $arrayType;
        }

        $types = [];

        if (! $keyType->isNull()->no()) {
            $types[] = $arrayType;
        }

        $keys = [];

        foreach ($keyType->getConstantScalarTypes() as $type) {
            if (! $type->isInteger()->yes() && ! $type->isString()->yes()) {
                continue;
            }

            $keys[] = $type;
        }

        if ($keys === []) {
            $types[] = $this->getOffsetType($arrayType, $keyType, $default);
        } else {
            foreach ($keys as $key) {
                $types[] = $this->getOffsetType($arrayType, $key, $default);
            }
        }

        return TypeCombinator::union(...$types);
    }

    private function getOffsetType(Type $array, Type $key, Type $default): Type
    {
        $hasOffset = $array->hasOffsetValueType($key);

        if ($hasOffset->yes()) {
            return $array->getOffsetValueType($key);
        }

        $types = [];

        if (! $hasOffset->no()) {
            $types[] = $array->getOffsetValueType($key);
        }

        $strings = $key->getConstantStrings();

        if ($strings !== [] && str_contains($strings[0]->getValue(), '.')) {
            $types[] = $this->getNestedOffsetType($array, explode('.', $strings[0]->getValue()), $default);
        } else {
            $types[] = $default;
        }

        return TypeCombinator::union(...$types);
    }

    /** @param non-empty-list<string> $segments */
    private function getNestedOffsetType(Type $array, array $segments, Type $default): Type
    {
        $possiblyMissing = false;

        foreach ($segments as $segment) {
            $key       = new ConstantStringType($segment);
            $hasOffset = $array->hasOffsetValueType($key);

            if ($hasOffset->no()) {
                return $default;
            }

            $possiblyMissing = $possiblyMissing || $hasOffset->maybe();
            $array           = $array->getOffsetValueType($key);
        }

        return $possiblyMissing ? TypeCombinator::union($array, $default) : $array;
    }

    private function resolveValueType(Type $type, Scope $scope): Type
    {
        return TypeTraverser::map($type, static function (Type $type, callable $traverse) use ($scope): Type {
            if ($type->isCallable()->yes()) {
                return $type->getCallableParametersAcceptors($scope)[0]->getReturnType();
            }

            return $traverse($type);
        });
    }
}
