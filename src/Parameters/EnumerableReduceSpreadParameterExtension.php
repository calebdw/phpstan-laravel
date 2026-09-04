<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Parameters;

use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\MethodParameterClosureTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

use function array_map;

final class EnumerableReduceSpreadParameterExtension implements MethodParameterClosureTypeExtension
{
    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return $methodReflection->getName() === 'reduceSpread'
            && $parameter->getName() === 'callback';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, ParameterReflection $parameter, Scope $scope): Type|null
    {
        $slots = $this->slots($methodCall, $scope);

        if ($slots === null) {
            return null;
        }

        return new ClosureType(array_map(static fn ($t) => new SimpleParameterReflection('item', $t), $slots), new MixedType());
    }

    /**
     * reduceSpread() does $callback(...$result, $value, $key).
     *
     * @return list<Type>|null
     */
    public function slots(MethodCall $methodCall, Scope $scope): array|null
    {
        $initial = $this->initialTypes($methodCall, $scope);

        if ($initial === null) {
            return null;
        }

        $calledOnType = $scope->getType($methodCall->var);

        return [
            ...$initial,
            $calledOnType->getTemplateType(Enumerable::class, 'TValue'),
            $calledOnType->getTemplateType(Enumerable::class, 'TKey'),
        ];
    }

    /** @return list<Type>|null */
    public function initialTypes(MethodCall $methodCall, Scope $scope): array|null
    {
        $types = [];

        foreach ($methodCall->getArgs() as $i => $arg) {
            if ($this->isCallbackArg($arg, $i)) {
                continue;
            }

            if ($arg->unpack) {
                return null;
            }

            $types[] = $scope->getType($arg->value)->generalize(GeneralizePrecision::lessSpecific());
        }

        return $types;
    }

    private function isCallbackArg(Arg $arg, int $i): bool
    {
        if ($arg->name !== null) {
            return $arg->name->toString() === 'callback';
        }

        return $i === 0;
    }
}
