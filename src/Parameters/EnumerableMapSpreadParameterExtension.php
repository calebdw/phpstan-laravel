<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Parameters;

use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\ClosureType;
use PHPStan\Type\MethodParameterClosureTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_map;
use function array_values;
use function in_array;

final class EnumerableMapSpreadParameterExtension implements MethodParameterClosureTypeExtension
{
    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return in_array($methodReflection->getName(), ['mapSpread', 'eachSpread'], true)
            && $parameter->getName() === 'callback';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): Type|null {
        $calledOnType = $scope->getType($methodCall->var);
        $slots        = $this->slots(
            $calledOnType->getTemplateType(Enumerable::class, 'TValue'),
            $calledOnType->getTemplateType(Enumerable::class, 'TKey'),
        );

        if ($slots === null) {
            return null;
        }

        return new ClosureType(array_map($this->parameter(...), $slots), new MixedType());
    }

    /** @return list<Type>|null */
    public function slots(Type $chunkType, Type $keyType): array|null
    {
        $slots = $this->spreadSlots($chunkType);

        if ($slots === null) {
            return null;
        }

        $slots[] = $keyType;

        return $slots;
    }

    private function parameter(Type $type): NativeParameterReflection
    {
        /** @phpstan-ignore phpstanApi.constructor */
        return new NativeParameterReflection(
            'item',
            false,
            $type,
            PassedByReference::createNo(),
            false,
            null,
        );
    }

    /**
     * mapSpread() does $callback(...$chunk) after appending the key.
     * Only a known list of slots (a constant array / array shape) can be
     * spread into named parameters.
     *
     * @return list<Type>|null
     */
    private function spreadSlots(Type $chunkType): array|null
    {
        $arrays = $chunkType->getConstantArrays();

        if ($arrays === []) {
            return null;
        }

        $slots = [];

        foreach ($arrays as $array) {
            foreach (array_values($array->getValueTypes()) as $i => $valueType) {
                $slots[$i] = isset($slots[$i])
                    ? TypeCombinator::union($slots[$i], $valueType)
                    : $valueType;
            }
        }

        return $slots === [] ? null : array_values($slots);
    }
}
