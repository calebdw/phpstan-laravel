<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ErrorType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Throwable;

final class AppMakeHelper
{
    public function __construct(
        private ContainerHelper $containerHelper,
        private readonly bool $strictContracts = false,
    ) {
    }

    public function resolveTypeFromCall(FuncCall|MethodCall|StaticCall $call, Scope $scope): Type|null
    {
        $args = $call->getArgs();

        if ($args === []) {
            return null;
        }

        $constantStrings = $scope->getType($args[0]->value)->getConstantStrings();

        if ($constantStrings === []) {
            return null;
        }

        $types = [];
        foreach ($constantStrings as $constantString) {
            // Take the argument at face value rather than asking the
            // container what it is bound to, so that code depending on a
            // contract is not silently typed as whatever concrete class
            // happens to be bound in this environment.
            if ($this->strictContracts && $constantString->isClassString()->yes()) {
                $types[] = $constantString->getClassStringObjectType();
                continue;
            }

            try {
                /** @var object|null $resolved */
                $resolved = $this->containerHelper->resolve($constantString->getValue());

                if ($resolved === null) {
                    if ($constantString->isClassString()->yes()) {
                        $types[] = $constantString->getClassStringObjectType();
                        continue;
                    }

                    return new ErrorType();
                }

                $types[] = new ObjectType($resolved::class);
            } catch (Throwable) {
                return new ErrorType();
            }
        }

        return TypeCombinator::union(...$types);
    }
}
