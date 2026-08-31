<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;

final class EnumerableKeyByExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'keyBy';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        $keyArg = $methodCall->getArg('keyBy', 0);

        if ($keyArg === null) {
            return null;
        }

        $calledOnType = $scope->getType($methodCall->var);
        $class        = $calledOnType->getObjectClassNames()[0] ?? null;

        if ($class === null) {
            return null;
        }

        // Unlike pluck, keyBy only rewrites the keys, so the value type and
        // the collection class both carry over unchanged.
        $valueType = $calledOnType->getTemplateType(Enumerable::class, 'TValue');

        return new GenericObjectType($class, [
            $this->columnHelper->normalizeKey(
                $this->columnHelper->getKeyType($valueType, $keyArg, $scope),
            ),
            $valueType,
        ]);
    }
}
