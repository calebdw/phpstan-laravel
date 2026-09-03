<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;

use function count;

final class RequestRouteExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Request::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'route';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $args = $methodCall->getArgs();

        if ($args === []) {
            return TypeCombinator::addNull(new ObjectType(Route::class));
        }

        if (count($args) === 2) {
            $defaultType = $scope->getType($args[1]->value);
        } else {
            $defaultType = new NullType();
        }

        return TypeUtils::toBenevolentUnion(TypeCombinator::union(
            new ObjectWithoutClassType(),
            new StringType(),
            $defaultType,
        ));
    }
}
