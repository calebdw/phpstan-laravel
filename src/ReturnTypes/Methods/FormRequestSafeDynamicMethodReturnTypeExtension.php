<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

use function assert;
use function count;

class FormRequestSafeDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return FormRequest::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'safe';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $args = $methodCall->getArgs();

        if (count($args) === 0) {
            return null;
        }

        $argType = $scope->getType($args[0]->value);

        if (! $argType->isConstantArray()->yes()) {
            return null;
        }

        /** @phpstan-ignore phpstanApi.instanceofType (asserting the concrete type is the point of the line) */
        assert($argType instanceof ConstantArrayType);

        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($argType->getValueTypes() as $keyType) {
            foreach ($keyType->getConstantStrings() as $constantString) {
                $builder->setOffsetValueType($constantString, new MixedType());
            }
        }

        return $builder->getArray();
    }
}
