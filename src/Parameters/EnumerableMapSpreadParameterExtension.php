<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Parameters;

use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\MethodParameterClosureTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

use function array_map;
use function in_array;

final class EnumerableMapSpreadParameterExtension implements MethodParameterClosureTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
    }

    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return in_array($methodReflection->getName(), ['mapSpread', 'eachSpread'], true)
            && $parameter->getName() === 'callback';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, ParameterReflection $parameter, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);
        $slots        = $this->columnHelper->spreadSlots(
            $calledOnType->getTemplateType(Enumerable::class, 'TValue'),
            $calledOnType->getTemplateType(Enumerable::class, 'TKey'),
        );

        if ($slots === null) {
            return null;
        }

        return new ClosureType(array_map(static fn ($t) => new SimpleParameterReflection('item', $t), $slots), new MixedType());
    }
}
