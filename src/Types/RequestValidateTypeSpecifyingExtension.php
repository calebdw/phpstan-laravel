<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Support\ValidationHelper;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\TypeCombinator;

use function in_array;

final class RequestValidateTypeSpecifyingExtension implements MethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function __construct(private ValidationHelper $validationHelper)
    {
    }

    public function getClass(): string
    {
        return Request::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection, MethodCall $node, TypeSpecifierContext $context): bool
    {
        return $context->null() && in_array($methodReflection->getName(), ['validate', 'validateWithBag'], true);
    }

    public function specifyTypes(MethodReflection $methodReflection, MethodCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
    {
        $rules = $node->getArg(
            'rules',
            $methodReflection->getName() === 'validateWithBag' ? 1 : 0,
        );

        if ($rules === null) {
            return new SpecifiedTypes();
        }

        $shape = $this->validationHelper->shapeFromRulesExpr($rules->value, $scope);

        if ($shape === null) {
            return new SpecifiedTypes();
        }

        $objectShape = $this->validationHelper->objectShape($shape);

        if ($objectShape === null) {
            return new SpecifiedTypes();
        }

        return $this->typeSpecifier->create(
            $node->var,
            TypeCombinator::intersect($scope->getType($node->var), $objectShape),
            TypeSpecifierContext::createTruthy(),
            $scope,
        );
    }

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }
}
