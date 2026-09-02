<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Type;

use function array_intersect;
use function collect;

/**
 * @phpstan-type FunctionArg array{
 *     functions: list<string>,
 *     parameter: string,
 *     position: int
 * }
 * @phpstan-type MethodArg array{
 *     methods: list<string>,
 *     parameter: string,
 *     position: int,
 *     receivers?: list<class-string>,
 *     trait?: class-string
 * }
 */
final class CallHelper
{
    public function __construct(private TypeHelper $typeHelper)
    {
    }

    /**
     * @param list<FunctionArg> $functions
     * @param list<MethodArg>   $methods
     */
    public function matchingArg(CallLike $node, Scope $scope, array $functions = [], array $methods = []): Expr|null
    {
        $names = $this->callNames($node, $scope);

        if ($names === []) {
            return null;
        }

        if ($node instanceof FuncCall) {
            return collect($functions)
                ->reject(static fn ($f) => array_intersect($names, $f['functions']) === [])
                ->map(static fn ($f) => $node->getArg($f['parameter'], $f['position'])?->value)
                ->filter()
                ->first();
        }

        if (! $node instanceof MethodCall && ! $node instanceof NullsafeMethodCall && ! $node instanceof StaticCall) {
            return null;
        }

        $receiver = $this->receiverType($node, $scope);

        return collect($methods)
            ->filter(function (array $method) use ($names, $receiver): bool {
                if (array_intersect($names, $method['methods']) === []) {
                    return false;
                }

                $trait     = $method['trait'] ?? null;
                $receivers = $method['receivers'] ?? null;

                return ($receivers !== null && $this->typeHelper->isCalledOn($receiver, $receivers))
                    || ($trait !== null && $this->typeHelper->usesTrait($receiver, $trait));
            })
            ->map(static fn ($m) => $node->getArg($m['parameter'], $m['position'])?->value)
            ->filter()
            ->first();
    }

    public function receiverType(MethodCall|NullsafeMethodCall|StaticCall $node, Scope $scope): Type
    {
        if ($node instanceof StaticCall) {
            $type = $node->class instanceof Name
                ? $scope->resolveTypeByName($node->class)
                : $scope->getType($node->class);

            return $type->getObjectTypeOrClassStringObjectType();
        }

        return $scope->getType($node->var);
    }

    /** @return list<string> */
    public function callNames(CallLike $node, Scope $scope): array
    {
        if ($node instanceof FuncCall) {
            if ($node->name instanceof Name) {
                return [$scope->resolveName($node->name)];
            }

            return $this->typeHelper->constantStrings($scope->getType($node->name));
        }

        if (! $node instanceof MethodCall && ! $node instanceof NullsafeMethodCall && ! $node instanceof StaticCall) {
            return [];
        }

        if ($node->name instanceof Identifier) {
            return [$node->name->toString()];
        }

        return $this->typeHelper->constantStrings($scope->getType($node->name));
    }
}
