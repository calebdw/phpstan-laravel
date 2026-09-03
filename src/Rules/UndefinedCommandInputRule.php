<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\ConsoleApplicationResolver;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;

/** @implements Rule<MethodCall> */
final class UndefinedCommandInputRule implements Rule
{
    public function __construct(
        private ConsoleApplicationResolver $consoleApplicationResolver,
        private CallHelper $callHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return RuleError[] errors */
    public function processNode(Node $node, Scope $scope): array
    {
        $methods = $this->callHelper->matchingNames($node, $scope, ['argument', 'option']);

        if ($methods === []) {
            return [];
        }

        $arg = $node->getArg('key', 0)?->value;

        if ($arg === null) {
            return [];
        }

        $inputs = $this->typeHelper->constantStrings($scope->getType($arg));

        if ($inputs === []) {
            return [];
        }

        $errors = [];

        foreach ($scope->getType($node->var)->getObjectClassReflections() as $classReflection) {
            foreach ($this->consoleApplicationResolver->findCommands($classReflection) as $name => $command) {
                $command->mergeApplicationDefinition(); // @phpstan-ignore method.internal (acceptable)
                $definition = $command->getDefinition();

                foreach ($methods as $method) {
                    foreach ($inputs as $input) {
                        $exists = $method === 'argument'
                            ? $definition->hasArgument($input)
                            : ($definition->hasOption($input) || $definition->hasShortcut($input));

                        if ($exists) {
                            continue;
                        }

                        $errors[] = $this->error($method, $name, $input, $node->getStartLine());
                    }
                }
            }
        }

        return $errors;
    }

    private function error(string $method, string $command, string $input, int $line): RuleError
    {
        if ($method === 'argument') {
            return RuleErrorBuilder::message(sprintf('Command "%s" does not have argument "%s".', $command, $input))
                ->line($line)
                ->identifier('laravel.console.undefinedArgument')
                ->build();
        }

        return RuleErrorBuilder::message(sprintf('Command "%s" does not have option "%s".', $command, $input))
            ->line($line)
            ->identifier('laravel.console.undefinedOption')
            ->build();
    }
}
