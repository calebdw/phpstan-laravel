<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ManagerHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Support\Manager;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class ManagerDriverExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ManagerHelper $managerHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function getClass(): string
    {
        return Manager::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'driver';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $managers = $scope->getType($methodCall->var)->getObjectClassReflections();
        $drivers  = $this->getRequestedDrivers($methodCall, $scope);

        if ($managers === [] || $drivers === null) {
            return null;
        }

        $types = [];

        foreach ($managers as $manager) {
            foreach ($drivers as $driver) {
                $type = $this->managerHelper->getDriverType($manager, $driver);

                if ($type === null) {
                    return null;
                }

                $types[] = $type;
            }
        }

        return $types === [] ? null : TypeCombinator::union(...$types);
    }

    /**
     * Returns the requested driver names, null being the default driver,
     * or null when the requested driver is not statically known.
     *
     * @return list<string|null>|null
     */
    private function getRequestedDrivers(MethodCall $methodCall, Scope $scope): array|null
    {
        $args = $methodCall->getArgs();

        if (! isset($args[0])) {
            return [null];
        }

        $type = $scope->getType($args[0]->value);

        if ($type->isNull()->yes()) {
            return [null];
        }

        $drivers = $this->typeHelper->constantStrings($type);

        if ($drivers === []) {
            return null;
        }

        if (! $type->isNull()->no()) {
            $drivers[] = null;
        }

        return $drivers;
    }
}
