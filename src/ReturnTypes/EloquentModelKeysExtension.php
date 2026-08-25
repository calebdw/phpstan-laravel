<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\ModelHelper;
use Illuminate\Database\Eloquent\Collection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class EloquentModelKeysExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ModelHelper $modelHelper,
        /** @var class-string */
        private string $class,
        private string $templateType,
    ) {
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'modelKeys';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $calledOnType = $scope->getType($methodCall->var);
        $modelType    = $calledOnType->getTemplateType($this->class, $this->templateType);
        $keyType      = $this->modelHelper->getModelKeyType($modelType);

        if ($this->class !== Collection::class) {
            return TypeCombinator::intersect(
                new ArrayType(new IntegerType(), $keyType),
                new AccessoryArrayListType(),
            );
        }

        return new ArrayType(
            $calledOnType->getTemplateType($this->class, 'TKey'),
            TypeCombinator::addNull($keyType),
        );
    }
}
