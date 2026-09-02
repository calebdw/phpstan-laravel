<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Ignore;

use CalebDW\PhpstanLaravel\Reflection\MacroMethodReflection;
use CalebDW\PhpstanLaravel\Support\CallHelper;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Type;

final class MacroStaticCallIgnoreExtension implements IgnoreErrorExtension
{
    public function __construct(
        /** @var list<class-string> */
        private array $staticMacroClasses,
        private CallHelper $callHelper,
    ) {
    }

    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ($error->getIdentifier() !== 'method.staticCall' || ! $node instanceof StaticCall) {
            return false;
        }

        $type    = $this->callHelper->receiverType($node, $scope);
        $methods = $this->callHelper->callNames($node, $scope);

        if ($methods === []) {
            return false;
        }

        foreach ($methods as $method) {
            if (! $this->isMacroStaticCall($type, $method, $scope)) {
                return false;
            }
        }

        return true;
    }

    private function isMacroStaticCall(Type $type, string $method, Scope $scope): bool
    {
        foreach ($type->getObjectClassReflections() as $classReflection) {
            if (! $classReflection->hasMethod($method) || $classReflection->hasNativeMethod($method)) {
                continue;
            }

            foreach ($this->staticMacroClasses as $staticMacroClass) {
                if (! $classReflection->is($staticMacroClass)) {
                    continue;
                }

                if ($classReflection->getMethod($method, $scope)->getPrototype() instanceof MacroMethodReflection) {
                    return true;
                }
            }
        }

        return false;
    }
}
