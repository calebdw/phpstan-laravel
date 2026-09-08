<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\FormRequestHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\ValidatedInput;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function count;

final class FormRequestSafeDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private FormRequestHelper $formRequestHelper,
        private TypeHelper $typeHelper,
    ) {
    }

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
        $shape = $this->shape($scope->getType($methodCall->var));
        $args  = $methodCall->getArgs();

        if (count($args) === 0) {
            return $shape === null ? null : new GenericObjectType(ValidatedInput::class, [$shape]);
        }

        $keys = $this->typeHelper->constantStrings($scope->getType($args[0]->value));

        if ($keys === []) {
            return null;
        }

        if ($shape !== null) {
            return $this->formRequestHelper->pick($shape, $keys);
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($keys as $key) {
            $builder->setOffsetValueType(new ConstantStringType($key), new MixedType());
        }

        return $builder->getArray();
    }

    private function shape(Type $calledOn): Type|null
    {
        $shapes = [];

        foreach ($calledOn->getObjectClassReflections() as $class) {
            $shape = $this->formRequestHelper->validatedShape($class);

            if ($shape === null) {
                continue;
            }

            $shapes[] = $shape;
        }

        return $shapes === [] ? null : TypeCombinator::union(...$shapes);
    }
}
