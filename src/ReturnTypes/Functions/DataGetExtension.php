<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Functions;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;

use function array_search;
use function array_slice;
use function explode;
use function in_array;

final class DataGetExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'data_get';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type|null
    {
        $targetArg = $functionCall->getArg('target', 0);
        $keyArg    = $functionCall->getArg('key', 1);

        if ($targetArg === null || $keyArg === null) {
            return null;
        }

        $target  = $scope->getType($targetArg->value);
        $key     = $scope->getType($keyArg->value);
        $default = $functionCall->getArg('default', 2);
        $default = $default === null
            ? new NullType()
            : $this->resolveDefault($scope->getType($default->value), $scope);

        if ($key->isNull()->yes()) {
            return $target;
        }

        $types = [];

        if (! $key->isNull()->no()) {
            $types[] = $target;
        }

        foreach ($key->getConstantStrings() as $string) {
            $types[] = $this->getPath($target, explode('.', $string->getValue()), $default, $scope);
        }

        foreach ($key->getConstantArrays() as $array) {
            $segments = [];

            foreach ($array->getValueTypes() as $value) {
                foreach ($value->getConstantStrings() as $string) {
                    $segments[] = $string->getValue();
                }
            }

            if ($segments === []) {
                continue;
            }

            $types[] = $this->getPath($target, $segments, $default, $scope);
        }

        return $types === [] ? null : TypeCombinator::union(...$types);
    }

    /** @param list<string> $segments */
    private function getPath(Type $target, array $segments, Type $default, Scope $scope): Type
    {
        $star = array_search('*', $segments, true);

        if ($star === false) {
            return $this->columnHelper->pluckFromType($target, $segments, $scope) ?? $default;
        }

        $prefix = array_slice($segments, 0, $star);
        $rest   = array_slice($segments, $star + 1);

        if ($prefix !== []) {
            $target = $this->columnHelper->pluckFromType($target, $prefix, $scope);

            if ($target === null) {
                return $default;
            }
        }

        if ($target->isIterable()->no()) {
            return $default;
        }

        $item = $rest === []
            ? $target->getIterableValueType()
            : $this->getPath($target->getIterableValueType(), $rest, $default, $scope);

        if (in_array('*', $rest, true) && $item->isIterable()->yes()) {
            $item = $item->getIterableValueType();
        }

        return TypeCombinator::intersect(
            new ArrayType(new IntegerType(), $item),
            new AccessoryArrayListType(),
        );
    }

    private function resolveDefault(Type $type, Scope $scope): Type
    {
        return TypeTraverser::map($type, static function (Type $type, callable $traverse) use ($scope): Type {
            if ($type->isCallable()->yes()) {
                return $type->getCallableParametersAcceptors($scope)[0]->getReturnType();
            }

            return $traverse($type);
        });
    }
}
