<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Type;

final class PaginatorGetCollectionExtension implements DynamicMethodReturnTypeExtension
{
    /** @param class-string $class */
    public function __construct(
        private CollectionHelper $collectionHelper,
        private string $class,
    ) {
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getCollection';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $valueType = $scope->getType($methodCall->var)->getTemplateType($this->class, 'TValue');

        return $valueType instanceof ErrorType
            ? null
            : $this->collectionHelper->determineCollectionTypeFromModels($valueType);
    }
}
