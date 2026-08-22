<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\ConfigHelper;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function in_array;

/** @internal */
final class ConfigDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    /** @param class-string $class */
    public function __construct(
        private ConfigHelper $configHelper,
        private string $class,
    ) {
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['get', 'getMany', 'array', 'collection', 'all'], true);
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        return $this->configHelper->determineConfigType($methodReflection, $methodCall, $scope);
    }
}
