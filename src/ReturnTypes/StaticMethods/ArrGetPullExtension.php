<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\ArrHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

use function array_map;
use function array_shift;
use function explode;
use function in_array;
use function str_contains;

#[AutowiredService]
final class ArrGetPullExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(
        private TypeHelper $typeHelper,
        private ArrHelper $arrHelper,
    ) {
    }

    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['get', 'pull'], true)
            && $methodReflection->getDeclaringClass()->getName() === Arr::class;
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $arrayArg = $methodCall->getArg('array', 0);
        $keyArg   = $methodCall->getArg('key', 1);

        if ($arrayArg === null || $keyArg === null) {
            return null;
        }

        $arrayType = $scope->getType($arrayArg->value);
        $keyType   = $scope->getType($keyArg->value);
        $default   = $methodCall->getArg('default', 2);
        $default   = $default === null ? new NullType() : $this->typeHelper->valueOf($scope->getType($default->value), $scope);

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
        if ($array instanceof UnionType) {
            return TypeCombinator::union(...array_map(fn ($t) => $this->getOffsetType($t, $key, $default), $array->getTypes()));
        }

        $hasOffset = $this->arrHelper->hasOffset($array, $key);

        if ($hasOffset->yes()) {
            return $array->getOffsetValueType($key);
        }

        $types = [];

        if (! $hasOffset->no()) {
            $types[] = $array->getOffsetValueType($key);
        }

        $strings = $key->getConstantStrings();

        if ($strings === []) {
            $types[] = $default;

            return TypeCombinator::union(...$types);
        }

        foreach ($strings as $string) {
            $value   = $string->getValue();
            $types[] = str_contains($value, '.')
                ? $this->getNestedOffsetType($array, explode('.', $value), $default)
                : $default;
        }

        return TypeCombinator::union(...$types);
    }

    /** @param non-empty-list<string> $segments */
    private function getNestedOffsetType(Type $array, array $segments, Type $default): Type
    {
        if ($array instanceof UnionType) {
            return TypeCombinator::union(...array_map(fn ($t) => $this->getNestedOffsetType($t, $segments, $default), $array->getTypes()));
        }

        $key       = new ConstantStringType(array_shift($segments));
        $hasOffset = $this->arrHelper->hasOffset($array, $key);

        if ($hasOffset->no()) {
            return $default;
        }

        $value = $array->getOffsetValueType($key);
        $value = $segments === [] ? $value : $this->getNestedOffsetType($value, $segments, $default);

        return $hasOffset->maybe() ? TypeCombinator::union($value, $default) : $value;
    }
}
