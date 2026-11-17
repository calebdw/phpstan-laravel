<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use Closure;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/** @implements Rule<FuncCall> */
#[RegisteredRule(level: 0)]
final class NoUselessValueFunctionCallsRule implements Rule
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
        if ($this->callHelper->matchingNames($node, $scope, 'value') === []) {
            return [];
        }

        $arg = $node->getArg('value', 0);

        if ($arg === null) {
            return [];
        }

        if (! (new ObjectType(Closure::class))->isSuperTypeOf($scope->getType($arg->value))->no()) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Calling the helper function 'value()' without a closure as the first argument simply returns the first argument without doing anything")
                ->line($node->getStartLine())
                ->identifier('laravel.uselessConstructs.value')
                ->fixNode($node, static fn () => $arg->value)
                ->build(),
        ];
    }
}
