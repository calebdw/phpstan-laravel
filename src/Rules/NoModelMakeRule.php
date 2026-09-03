<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Catches inefficient instantiation of models using Model::make().
 *
 * For example:
 * User::make()
 *
 * It is functionally equivalent to simply use the constructor:
 * new User()
 *
 * @implements Rule<StaticCall>
 */
class NoModelMakeRule implements Rule
{
    public function __construct(
        private CallHelper $callHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /** @return array<int, RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->callHelper->matchingNames($node, $scope, 'make') === []) {
            return [];
        }

        if (! $this->callHelper->isCalledOn($node, $scope, Model::class)) {
            return [];
        }

        return [
            /** @phpstan-ignore method.internal (still experimental) */
            RuleErrorBuilder::message("Called 'Model::make()' which performs unnecessary work, use 'new Model()'.")
                ->identifier('laravel.modelMake')
                ->line($node->getStartLine())
                ->file($scope->getFile(), $scope->getFileDescription())
                ->fixNode($node, static fn (StaticCall $n) => new New_($n->class, $n->args))
                ->build(),
        ];
    }
}
