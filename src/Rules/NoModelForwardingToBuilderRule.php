<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

use function sprintf;

/** @implements Rule<MethodCall> */
final class NoModelForwardingToBuilderRule implements Rule
{
    public function __construct(private CallHelper $callHelper)
    {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @inheritDoc */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        $calledMethods = $this->callHelper->callNames($node, $scope);

        $calledOnType = TypeCombinator::removeNull($scope->getType($node->var));

        foreach ($calledOnType->getObjectClassReflections() as $classReflection) {
            if (! $classReflection->is(Model::class)) {
                continue;
            }

            foreach ($calledMethods as $method) {
                if (! $classReflection->hasMethod($method)) {
                    continue;
                }

                $methodReflection = $classReflection->getMethod($method, $scope);
                $declaringClass   = $methodReflection->getDeclaringClass();

                if (
                    ! $declaringClass->is(QueryBuilder::class)
                    && ! $declaringClass->is(EloquentBuilder::class)
                    // Override for the with() method, which is also a static method
                    // on the Model class, so is not caught by the above check.
                    // Should not be called on a model instance when this rule is enabled.
                    && $method !== 'with'
                ) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf('Method [%s] is forwarded to a Builder instance, which is not allowed.', $method))
                    ->tip(sprintf('Use [::%s()], [::query()->%s()] or [->newQuery()->%s()] instead.', $method, $method, $method))
                    ->identifier('laravel.modelForwardingToBuilder')
                    ->line($node->name->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}
