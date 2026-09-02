<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;

/** @implements Rule<StaticCall> */
final class NoModelStaticForwardingToBuilderRule implements Rule
{
    public function __construct(private CallHelper $callHelper)
    {
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /** @inheritDoc */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        $calledMethods = $this->callHelper->callNames($node, $scope);
        $calledOnType  = $this->callHelper->receiverType($node, $scope);

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

                if (! $declaringClass->is(QueryBuilder::class) && ! $declaringClass->is(EloquentBuilder::class)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf('Static method [%s] is forwarded to a Builder instance, which is not allowed.', $method))
                    ->tip(sprintf('Use [::query()->%s()] instead.', $method))
                    ->identifier('laravel.modelStaticForwardingToBuilder')
                    ->line($node->name->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}
