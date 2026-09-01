<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ConsoleApplicationHelper;
use CalebDW\PhpstanLaravel\Support\ConsoleApplicationResolver;
use Illuminate\Console\Command;
use InvalidArgumentException;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_unique;
use function count;
use function in_array;

class CommandArgumentDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ConsoleApplicationResolver $consoleApplicationResolver,
        private ConsoleApplicationHelper $consoleApplicationHelper,
    ) {
    }

    public function getClass(): string
    {
        return Command::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['argument', 'arguments', 'hasArgument'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return null;
        }

        $args = $methodCall->getArgs();

        if ($methodReflection->getName() === 'hasArgument') {
            if ($args === []) {
                return null;
            }

            $constantStrings = $scope->getType($args[0]->value)->getConstantStrings();

            if (count($constantStrings) !== 1) {
                return null;
            }

            $returnTypes = [];

            foreach ($this->consoleApplicationResolver->findCommands($classReflection) as $command) {
                $command->mergeApplicationDefinition(); // @phpstan-ignore method.internal (acceptable)
                $returnTypes[] = $command->getDefinition()->hasArgument($constantStrings[0]->getValue());
            }

            $returnTypes = array_unique($returnTypes);

            return count($returnTypes) === 1 ? new ConstantBooleanType($returnTypes[0]) : null;
        }

        if ($args === [] || $methodReflection->getName() === 'arguments') {
            return $this->consoleApplicationHelper->getArguments($classReflection, $scope);
        }

        $argStrings = $scope->getType($args[0]->value)->getConstantStrings();

        if (count($argStrings) === 0) {
            return null;
        }

        $returnTypes       = [];
        $defaultReturnType = ParametersAcceptorSelector::selectFromArgs($scope, $args, $methodReflection->getVariants())->getReturnType();

        foreach ($argStrings as $argString) {
            $argName = $argString->getValue();

            $argTypes = [];

            foreach ($this->consoleApplicationResolver->findCommands($classReflection) as $command) {
                try {
                    $command->mergeApplicationDefinition(); // @phpstan-ignore method.internal (acceptable)
                    $argument = $command->getDefinition()->getArgument($argName);

                    $argTypes[] = $this->consoleApplicationHelper->getArgumentType($scope, $argument);
                } catch (InvalidArgumentException) {
                }
            }

            $returnTypes[] = count($argTypes) > 0 ? TypeCombinator::union(...$argTypes) : $defaultReturnType;
        }

        return TypeCombinator::union(...$returnTypes);
    }
}
