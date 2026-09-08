<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules\Queue;

use CalebDW\PhpstanLaravel\Support\QueuedJobHelper;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionMethod;

use function sprintf;

/**
 * Cancelling a batch only stops future jobs. Jobs already on the queue still
 * run unless they check cancelled() or register SkipIfBatchCancelled.
 *
 * @implements Rule<InClassNode>
 */
class BatchableJobChecksCancellationRule implements Rule
{
    private const array GUARD_METHODS = ['handle', 'middleware'];

    /** @var array<string, array<Node>> */
    private array $parsed = [];

    public function __construct(
        private Parser $parser,
        private QueuedJobHelper $queuedJobHelper,
        private NodeFinder $nodeFinder = new NodeFinder(),
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();

        if (
            ! $this->queuedJobHelper->isConcrete($class, ShouldQueue::class)
            || ! $class->hasTraitUse(Batchable::class)
            || $this->guardsCancellation($class)
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Batchable job %s does not check for batch cancellation.',
                $class->getDisplayName(),
            ))
                ->tip('Check $this->batch()?->cancelled() or use the SkipIfBatchCancelled middleware.')
                ->identifier('laravel.batchableJob.missingCancellationCheck')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function guardsCancellation(ClassReflection $class): bool
    {
        $native = $class->getNativeReflection();

        foreach (self::GUARD_METHODS as $name) {
            if (! $native->hasMethod($name)) {
                continue;
            }

            $method = $this->methodNode($native->getMethod($name));

            if ($method !== null && $this->guards($method)) {
                return true;
            }
        }

        return false;
    }

    private function methodNode(ReflectionMethod $method): ClassMethod|null
    {
        $fileName = $method->getFileName();

        if ($fileName === false) {
            return null;
        }

        try {
            $stmts = $this->parsed[$fileName] ??= $this->parser->parseFile($fileName);
        } catch (ParserErrorsException) {
            return null;
        }

        $name  = $method->getName();
        $start = $method->getStartLine();
        $found = $this->nodeFinder->findFirst(
            $stmts,
            static fn (Node $node): bool => $node instanceof ClassMethod
                && $node->name->toString() === $name
                && $node->getStartLine() === $start,
        );

        return $found instanceof ClassMethod ? $found : null;
    }

    private function guards(ClassMethod $method): bool
    {
        return $this->nodeFinder->findFirst(
            [$method],
            static fn (Node $node): bool => (
                ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
                && $node->name instanceof Identifier
                && $node->name->toString() === 'cancelled'
            ) || (
                $node instanceof Name
                && ($node->toString() === SkipIfBatchCancelled::class || $node->getLast() === 'SkipIfBatchCancelled')
            ),
        ) !== null;
    }
}
