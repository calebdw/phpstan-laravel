<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<FuncCall> */
#[RegisteredRule(level: 0)]
final class NoUselessWithFunctionCallsRule implements Rule
{
    public function __construct(private CallHelper $callHelper)
    {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @return RuleError[] */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->callHelper->matchingNames($node, $scope, 'with') === []) {
            return [];
        }

        $value    = $node->getArg('value', 0);
        $callback = $node->getArg('callback', 1);

        if ($value === null) {
            return [];
        }

        if ($callback !== null && $scope->getType($callback->value)->isCallable()->no() === false) {
            return [];
        }

        $message = $callback === null
            ? "Calling the helper function 'with()' with only one argument simply returns the value itself. If you want to chain methods on a construct, use '(new ClassName())->foo()' instead"
            : "Calling the helper function 'with()' without a callable as the second argument simply returns the value without doing anything";

        return [
            RuleErrorBuilder::message($message)
                ->line($node->getStartLine())
                ->identifier('laravel.uselessConstructs.with')
                ->fixNode($node, static fn () => $value->value)
                ->build(),
        ];
    }
}
