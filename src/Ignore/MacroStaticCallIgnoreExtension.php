<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Ignore;

use CalebDW\PhpstanLaravel\Methods\Macro;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassMemberReflection;

final class MacroStaticCallIgnoreExtension implements IgnoreErrorExtension
{
    public function __construct(
        /** @var list<class-string> */
        private array $staticMacroClasses,
    ) {
    }

    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if (
            $error->getIdentifier() !== 'method.staticCall'
            || ! $node instanceof StaticCall
            || ! $node->name instanceof Identifier
        ) {
            return false;
        }

        $type = $node->class instanceof Name
            ? $scope->resolveTypeByName($node->class)
            : $scope->getType($node->class);

        $method = $node->name->toString();

        foreach ($type->getObjectClassReflections() as $classReflection) {
            if (! $classReflection->hasMethod($method) || $classReflection->hasNativeMethod($method)) {
                continue;
            }

            foreach ($this->staticMacroClasses as $staticMacroClass) {
                if (! $classReflection->is($staticMacroClass)) {
                    continue;
                }

                if ($this->isMacro($classReflection->getMethod($method, $scope)->getPrototype())) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isMacro(ClassMemberReflection $reflection): bool
    {
        return $reflection instanceof Macro;
    }
}
