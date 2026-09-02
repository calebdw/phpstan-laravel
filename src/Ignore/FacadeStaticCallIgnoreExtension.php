<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Ignore;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Support\Facades\Facade;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;

final class FacadeStaticCallIgnoreExtension implements IgnoreErrorExtension
{
    public function __construct(
        private CallHelper $callHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ($error->getIdentifier() !== 'method.staticCall' || ! $node instanceof StaticCall) {
            return false;
        }

        $type    = $this->callHelper->receiverType($node, $scope);
        $methods = $this->callHelper->callNames($node, $scope);

        if ($methods === [] || ! $this->typeHelper->isCalledOn($type, Facade::class)) {
            return false;
        }

        foreach ($type->getObjectClassReflections() as $classReflection) {
            foreach ($methods as $method) {
                if ($classReflection->hasNativeMethod($method)) {
                    return false;
                }
            }
        }

        return true;
    }
}
