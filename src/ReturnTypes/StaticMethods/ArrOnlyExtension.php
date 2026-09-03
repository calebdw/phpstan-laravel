<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class ArrOnlyExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'only';
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

        // Replays the framework's own array_intersect_key($array, array_flip((array) $keys)),
        // which leaves PHP's key semantics - integer-like string keys, keys absent from the
        // subset, optional keys surviving as optional - to PHPStan instead of restating them.
        // Unlike Arr::pluck this is a flat key intersection, so there is no dot notation to
        // resolve and nothing for the column helper to do.
        return $arrayType->intersectKeyArray(
            $scope->getType($keysArg->value)->toArray()->flipArray(),
        );
    }
}
