<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

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
    public function __construct(protected ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /** @return array<int, RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $name = $node->name;

        if (! $name instanceof Identifier) {
            return [];
        }

        if ($name->name !== 'make') {
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
        $class = $call->class;

        if ($class instanceof Expr) {
            $type = $scope->getType($class);

            if ($type->isClassString()->yes() && $type->getConstantStrings() !== []) {
                $type = new ObjectType($type->getConstantStrings()[0]->getValue());
            }
        } else {
            // Resolves every name form, so self, static and parent are
            // covered as well as a written-out class name.
            $type = $scope->resolveTypeByName($class);
        }

        return (new ObjectType(Model::class))->isSuperTypeOf($type)->yes();
    }
}
