<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\StaticMethodParameterOutTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_map;
use function array_shift;
use function explode;
use function is_string;
use function str_contains;

#[AutowiredService]
final class ArrPullParameterOutTypeExtension implements StaticMethodParameterOutTypeExtension
{
    public function isStaticMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return $methodReflection->getName() === 'pull'
            && $parameter->getName() === 'array'
            && $methodReflection->getDeclaringClass()->getName() === Arr::class;
    }

    public function getParameterOutTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): Type|null {
        $arrayArg = $methodCall->getArg('array', 0);
        $keyArg   = $methodCall->getArg('key', 1);

        if ($arrayArg === null || $keyArg === null) {
            return null;
        }

        $arrayType = $scope->getType($arrayArg->value);

        if (! $arrayType->isArray()->yes()) {
            return null;
        }

        $keys = array_filter(
            $scope->getType($keyArg->value)->getConstantScalarTypes(),
            static fn ($t) => $t->isInteger()->yes() || $t->isString()->yes(),
        );

        if ($keys === []) {
            return null;
        }

        $types = [];

        foreach ($keys as $key) {
            $types[] = $this->unsetKey($arrayType, $key);
        }

        return TypeCombinator::union(...$types);
    }

    private function unsetKey(Type $array, ConstantScalarType $key): Type
    {
        if ($array instanceof UnionType) {
            return TypeCombinator::union(...array_map(fn ($t) => $this->unsetKey($t, $key), $array->getTypes()));
        }

        $key = $key->toArrayKey();

        $hasOffset = $array->hasOffsetValueType($key);

        if ($hasOffset->yes()) {
            return $array->unsetOffset($key);
        }

        $values = $key->getConstantScalarValues();
        Assert::count($values, 1);

        $value = $values[0];

        if (! is_string($value) || ! str_contains($value, '.')) {
            return $array->unsetOffset($key);
        }

        $withoutLiteral = $array->unsetOffset($key);
        $withoutPath    = $this->unsetPath($withoutLiteral, explode('.', $value));

        return $hasOffset->maybe() ? TypeCombinator::union($withoutLiteral, $withoutPath) : $withoutPath;
    }

    /** @param non-empty-list<string> $segments */
    private function unsetPath(Type $array, array $segments): Type
    {
        if (! $array->isArray()->yes()) {
            return $array;
        }

        $key = (new ConstantStringType(array_shift($segments)))->toArrayKey();

        if ($segments === []) {
            return $array->unsetOffset($key);
        }

        if ($array->hasOffsetValueType($key)->no()) {
            return $array;
        }

        return $array->setExistingOffsetValueType(
            $key,
            $this->unsetPath($array->getOffsetValueType($key), $segments),
        );
    }
}
