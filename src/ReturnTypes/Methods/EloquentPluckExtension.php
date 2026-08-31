<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

/**
 * Resolves pluck() against the model behind an Eloquent builder or relation.
 *
 * A relation forwards unknown calls to its underlying builder, so the two read
 * the same column off the same model. They differ only in which template
 * parameter names that model.
 */
final class EloquentPluckExtension implements DynamicMethodReturnTypeExtension
{
    /**
     * @param class-string $class        receiver the extension is registered for
     * @param string       $templateType template parameter naming the model
     */
    public function __construct(
        private ColumnHelper $columnHelper,
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
        return $methodReflection->getName() === 'pluck';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        $valueArg = $methodCall->getArg('value', 0);
        $keyArg   = $methodCall->getArg('key', 1);

        if ($valueArg === null) {
            return null;
        }

        $from = $scope->getType($methodCall->var)->getTemplateType($this->class, $this->templateType);

        return $this->columnHelper->getCollectionType($from, $valueArg, $keyArg, $scope);
    }
}
