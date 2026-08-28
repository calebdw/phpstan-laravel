<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\StaticMethodParameterOutTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_shift;
use function explode;
use function is_string;
use function str_contains;

/** @internal */
final class ArrPullParameterOutTypeExtension implements StaticMethodParameterOutTypeExtension
{
    public function isStaticMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return $methodReflection->getDeclaringClass()->is(Arr::class)
            && $methodReflection->getName() === 'pull'
            && $parameter->getName() === 'array';
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
        if (! $array->hasOffsetValueType($key)->no()) {
            return $array->unsetOffset($key);
        }

        $values = $key->getConstantScalarValues();
        Assert::count($values, 1);

        $value = $values[0];

        if (! is_string($value) || ! str_contains($value, '.')) {
            return $array;
        }

        return $this->unsetPath($array, explode('.', $value));
    }

    /** @param non-empty-list<string> $segments */
    private function unsetPath(Type $array, array $segments): Type
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
            $this->unsetPath($array->getOffsetValueType($key), $segments),
        );
    }
}
