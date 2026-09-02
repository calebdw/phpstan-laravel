<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function in_array;

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
        private TypeHelper $typeHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /** @return array<int, RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! in_array('make', $this->callHelper->callNames($node, $scope), true)) {
            return [];
        }

        if (! $this->isCalledOnModel($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Called 'Model::make()' which performs unnecessary work, use 'new Model()'.")
                ->identifier('laravel.modelMake')
                ->line($node->getStartLine())
                ->file($scope->getFile(), $scope->getFileDescription())
                ->build(),
        ];
    }

    /**
     * Was the expression called on a Model instance?
     */
    protected function isCalledOnModel(StaticCall $call, Scope $scope): bool
    {
        return $this->typeHelper->isCalledOn($this->callHelper->receiverType($call, $scope), Model::class);
    }
}
