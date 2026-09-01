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

class CommandOptionDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
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
        return in_array($methodReflection->getName(), ['option', 'options', 'hasOption'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return null;
        }

        $args = $methodCall->getArgs();

        if ($methodReflection->getName() === 'hasOption') {
            if ($args === []) {
                return null;
            }

            $constantStrings = $scope->getType($args[0]->value)->getConstantStrings();

            if (count($constantStrings) !== 1) {
                return null;
            }

            $argName     = $constantStrings[0]->getValue();
            $returnTypes = [];

            foreach ($this->consoleApplicationResolver->findCommands($classReflection) as $command) {
                $command->mergeApplicationDefinition(); // @phpstan-ignore method.internal (acceptable)
                $definition    = $command->getDefinition();
                $returnTypes[] = $definition->hasOption($argName) || $definition->hasShortcut($argName);
            }

            $returnTypes = array_unique($returnTypes);

            return count($returnTypes) === 1 ? new ConstantBooleanType($returnTypes[0]) : null;
        }

        if ($args === [] || $methodReflection->getName() === 'options') {
            return $this->consoleApplicationHelper->getOptions($classReflection, $scope);
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
                    $definition = $command->getDefinition();

                    if ($definition->hasShortcut($argName)) {
                        $argument = $definition->getOptionForShortcut($argName);
                    } else {
                        $argument = $definition->getOption($argName);
                    }

                    $argTypes[] = $this->consoleApplicationHelper->getOptionType($scope, $argument);
                } catch (InvalidArgumentException) {
                }
            }

            $returnTypes[] = count($argTypes) > 0 ? TypeCombinator::union(...$argTypes) : $defaultReturnType;
        }

        return TypeCombinator::union(...$returnTypes);
    }
}
