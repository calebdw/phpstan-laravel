<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Collectors;

use Illuminate\View\ViewName;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

use function count;

/** @implements Collector<Node\Expr\FuncCall, string> */
final class UsedViewFunctionCollector implements Collector
{
    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    /** @param Node\Expr\FuncCall $node */
    public function processNode(Node $node, Scope $scope): string|null
    {
        $funcName = $node->name;

        if (! $funcName instanceof Node\Name) {
            return null;
        }

        $funcName = $scope->resolveName($funcName);

        // Any global `view` is taken to be Laravel's helper. A project is free
        // to define its own, but the only consequence is counting its argument
        // as a used view, which makes UnusedViewsRule report less rather than
        // report something wrong.
        if ($funcName !== 'view') {
            return null;
        }

        if (count($node->getArgs()) < 1) {
            return null;
        }

        $template = $node->getArgs()[0]->value;

        if (! $template instanceof Node\Scalar\String_) {
            return null;
        }

        return ViewName::normalize($template->value);
    }
}
