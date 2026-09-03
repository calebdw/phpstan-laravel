<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ClosureType;
use PHPStan\Type\MixedType;

/** @implements Rule<FuncCall> */
class NoUselessValueFunctionCallsRule implements Rule
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

        $args = $node->getArgs();

        if ($args === []) {
            return [];
        }

        if ($scope->getType($args[0]->value)->isSuperTypeOf(new ClosureType([], new MixedType(), true))->no() === false) {
            return [];
        }

        return [
            /** @phpstan-ignore method.internal (still experimental) */
            RuleErrorBuilder::message("Calling the helper function 'value()' without a closure as the first argument simply returns the first argument without doing anything")
                ->line($node->getStartLine())
                ->identifier('laravel.uselessConstructs.value')
                ->fixNode($node, static fn () => $args[0]->value)
                ->build(),
        ];
    }
}
