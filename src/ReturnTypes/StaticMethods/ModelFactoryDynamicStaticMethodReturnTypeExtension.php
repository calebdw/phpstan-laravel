<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\FactoryHelper;
use CalebDW\PhpstanLaravel\Types\ModelFactoryType;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class ModelFactoryDynamicStaticMethodReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(
        private CallHelper $callHelper,
        private FactoryHelper $factoryHelper,
    ) {
    }

    public function getClass(): string
    {
        return Model::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'factory';
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
    {
        $calledOnType = $this->callHelper->receiverType($methodCall, $scope);
        $args         = $methodCall->getArgs();

        if ($args === []) {
            $isSingleModel = TrinaryLogic::createYes();
        } else {
            $argType = $scope->getType($args[0]->value);

            $numericType = TypeCombinator::union(
                new IntegerType(),
                new FloatType(),
                TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType()),
            );

            $isSingleModel = $numericType->isSuperTypeOf($argType)->negate()->result;
        }

        return $this->factoryHelper->determineFactoryType(
            $calledOnType,
            static fn ($factory) => new ModelFactoryType($factory->getName(), null, $factory, $isSingleModel),
        );
    }
}
