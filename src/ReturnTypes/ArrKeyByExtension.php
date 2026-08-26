<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

#[AutowiredService]
final class ArrKeyByExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'keyBy';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type|null {
        $arrayArg = $methodCall->getArg('array', 0);
        $keyByArg = $methodCall->getArg('keyBy', 1);

        if ($arrayArg === null || $keyByArg === null) {
            return null;
        }

        $valueType = $scope->getType($arrayArg->value)->getIterableValueType();

        return new ArrayType(
            $this->columnHelper->normalizeKey(
                $this->columnHelper->getKeyType($valueType, $keyByArg, $scope),
            ),
            $valueType,
        );
    }
}
