<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Enumerable;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * This rule checks for unnecessary calls `Enumerable::toArray()` that
 * could have used `all()` instead. The `toArray()` method recursively
 * converts all Arrayable items in the Enumerable to an array and if
 * none of the items are Arrayable, it is unnecessary map call.
 *
 * For example:
 *     collect([1, 2, 3])->toArray()
 * could be simplified to:
 *      collect([1, 2, 3])->all()
 *
 * @implements Rule<MethodCall>
 */
final class NoUnnecessaryEnumerableToArrayCallsRule implements Rule
{
    public function __construct(
        private CallHelper $callHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return RuleError[] */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->callHelper->matchingNames($node, $scope, 'toArray') === []) {
            return [];
        }

        $calledOnType = $scope->getType($node->var);

        if (! $this->typeHelper->isCalledOn($calledOnType, Enumerable::class)) {
            return [];
        }

        $valueType = $calledOnType->getTemplateType(Enumerable::class, 'TValue');

        if (! (new ObjectType(Arrayable::class))->isSuperTypeOf($valueType)->no()) {
            return [];
        }

        return [
            /** @phpstan-ignore method.internal (still experimental) */
            RuleErrorBuilder::message(
                'Called [toArray()] on an Enumerable which does not contain any Arrayables.',
            )
                ->tip('Use [all()] to get the items as an array.')
                ->identifier('laravel.unnecessaryEnumerableToArrayCall')
                ->line($node->name->getStartLine())
                ->fixNode($node, static function (MethodCall $n) {
                    $n->name = new Identifier('all');

                    return $n;
                })
                ->build(),
        ];
    }
}
