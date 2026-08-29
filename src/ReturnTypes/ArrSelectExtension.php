<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\SelectHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class ArrSelectExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private SelectHelper $selectHelper)
    {
    }

    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'select';
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $arrayArg = $methodCall->getArg('array', 0);
        $keysArg  = $methodCall->getArg('keys', 1);

        if ($arrayArg === null || $keysArg === null) {
            return null;
        }

        return $this->selectHelper->selectItems(
            $scope->getType($arrayArg->value),
            $scope->getType($keysArg->value),
            $scope,
        );
    }
}
