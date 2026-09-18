<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\ReflectionHelper;
use Illuminate\Database\Eloquent\Attributes\Scope as ScopeAttribute;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;

/** @implements Rule<StaticCall> */
final class NoModelStaticForwardingToBuilderRule implements Rule
{
    public function __construct(
        private CallHelper $callHelper,
        private ReflectionHelper $reflectionHelper,
    ) {
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

                if ($classReflection->hasNativeMethod($method)) {
                    $nativeMethod = $classReflection->getNativeMethod($method);

                    if ($scope->canCallMethod($nativeMethod)) {
                        continue;
                    }

                    if ($this->reflectionHelper->hasMethodAttribute($nativeMethod, ScopeAttribute::class)) {
                        $errors[] = $this->error($method, $node);

                        continue;
                    }
                }

                $methodReflection = $classReflection->getMethod($method, $scope);
                $declaringClass   = $methodReflection->getDeclaringClass();

                if (! $declaringClass->is(QueryBuilder::class) && ! $declaringClass->is(EloquentBuilder::class)) {
                    continue;
                }

                $errors[] = $this->error($method, $node);
            }
        }

        return $errors;
    }

    private function error(string $method, StaticCall $node): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf('Static method [%s] is forwarded to a Builder instance, which is not allowed.', $method))
            ->tip(sprintf('Use [::query()->%s()] instead.', $method))
            ->identifier('laravel.modelStaticForwardingToBuilder')
            ->line($node->name->getStartLine())
            ->build();
    }
}
