<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

use function count;

final class OrWhereClosureHelper
{
    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return $methodReflection->getName() === 'orWhere'
            && $parameter->getName() === 'column'
            && $methodReflection->getDeclaringClass()->is(EloquentBuilder::class);
    }

    public function getTypeFromMethodCall(MethodCall|StaticCall $methodCall, ParameterReflection $parameter): Type|null
    {
        $args = $methodCall->getArgs();

        if (count($args) !== 2) {
            return null;
        }

        foreach ($args as $arg) {
            if ($arg->unpack) {
                return null;
            }
        }

        return TypeTraverser::map($parameter->getType(), static function (Type $type, callable $traverse): Type {
            if ($type instanceof ClosureType) {
                return $type->traverse(static fn (Type $t): Type => (new ObjectType(EloquentBuilder::class))->isSuperTypeOf($t)->yes()
                    ? new ObjectType(QueryBuilder::class)
                    : $t);
            }

            return $traverse($type);
        });
    }
}
