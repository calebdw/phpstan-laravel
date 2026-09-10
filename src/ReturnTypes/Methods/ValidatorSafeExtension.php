<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\TypeHelper;
use CalebDW\PhpstanLaravel\Support\ValidationHelper;
use Illuminate\Validation\Validator;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

use function count;

final class ValidatorSafeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ValidationHelper $validationHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function getClass(): string
    {
        return Validator::class;
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

        $keys = $this->typeHelper->constantStrings($scope->getType($args[0]->value));

        if ($keys === []) {
            return null;
        }

        $shape = $this->validationHelper->validatedShapeFromType($scope->getType($methodCall->var));

        if ($shape !== null) {
            return $this->validationHelper->pick($shape, $keys);
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($keys as $key) {
            $builder->setOffsetValueType(new ConstantStringType($key), new MixedType());
        }

        return $builder->getArray();
    }
}
