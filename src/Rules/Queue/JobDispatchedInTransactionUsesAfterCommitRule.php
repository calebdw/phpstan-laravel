<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules\Queue;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function count;
use function is_array;
use function is_bool;
use function is_string;
use function spl_object_id;
use function sprintf;

/**
 * A queued job pushed during an open transaction can run before commit, or
 * against rows a rollback threw away. afterCommit holds the dispatch until
 * the outermost transaction commits.
 *
 * @implements Rule<StaticCall>
 */
class JobDispatchedInTransactionUsesAfterCommitRule implements Rule
{
    /** @var array<string, bool> */
    private array $afterCommitByConnection = [];

    private Repository|false|null $config = false;

    public function __construct(
        private CallHelper $callHelper,
        private ContainerHelper $containerHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     *
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->isTransactionCall($node, $scope)) {
            return [];
        }

        $bodyNodes = $this->closureBody($node->getArg('callback', 0)?->value);

        if ($bodyNodes === []) {
            return [];
        }

        $dispatches  = [];
        $protected   = [];
        $connections = [];

        foreach ($bodyNodes as $bodyNode) {
            $this->visit($bodyNode, $scope, $dispatches, $protected, $connections);
        }

        $errors = [];

        foreach ($dispatches as $dispatch) {
            $id = spl_object_id($dispatch);

            if (($protected[$id] ?? null) === true) {
                continue;
            }

            $job = $this->dispatchedJobNeedingAfterCommit(
                $dispatch,
                $scope,
                $protected[$id] ?? null,
                $connections[$id] ?? null,
            );

            if ($job === null) {
                continue;
            }

            /** @phpstan-ignore method.internal (still experimental) */
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Job %s is dispatched inside a transaction without deferring until commit.',
                $job->getDisplayName(),
            ))
                ->tip('Call afterCommit() on the dispatch or implement ShouldQueueAfterCommit.')
                ->identifier('laravel.dispatchInTransaction.missingAfterCommit')
                ->line($dispatch->getStartLine())
                ->fixNode($dispatch, static fn (Node $n): Node => $n instanceof Expr
                    ? new MethodCall($n, new Identifier('afterCommit'))
                    : $n)
                ->build();
        }

        return $errors;
    }

    private function isTransactionCall(StaticCall $node, Scope $scope): bool
    {
        return $this->callHelper->matchingNames($node, $scope, 'transaction') !== []
            && $this->callHelper->isCalledOn($node, $scope, DB::class);
    }

    /** @return Node[] */
    private function closureBody(Expr|null $callback): array
    {
        return $callback instanceof FunctionLike ? $callback->getStmts() ?? [] : [];
    }

    /**
     * @param list<Node>         $dispatches
     * @param array<int, bool>   $protected
     * @param array<int, string> $connections
     */
    private function visit(
        Node $node,
        Scope $scope,
        array &$dispatches,
        array &$protected,
        array &$connections,
    ): void {
        if ($node instanceof StaticCall && $this->isTransactionCall($node, $scope)) {
            return;
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            $commit = $this->callHelper->matchingNames($node, $scope, ['afterCommit', 'beforeCommit']);

            if ($commit !== []) {
                $guarded = $this->dispatchInReceiverChain($node, $scope);

                if ($guarded !== null) {
                    $protected[spl_object_id($guarded)] ??= $commit[0] === 'afterCommit';
                }
            }

            if ($this->callHelper->matchingNames($node, $scope, 'onConnection') !== []) {
                $guarded = $this->dispatchInReceiverChain($node, $scope);
                $name    = $node->getArg('connection', 0)?->value;

                if ($guarded !== null && $name !== null) {
                    $strings = $scope->getType($name)->getConstantStrings();

                    if (count($strings) === 1) {
                        $connections[spl_object_id($guarded)] = $strings[0]->getValue();
                    }
                }
            }
        }

        if ($this->isDispatchCall($node, $scope)) {
            $dispatches[] = $node;
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $sub      = $node->{$subNodeName};
            $children = is_array($sub) ? $sub : [$sub];

            foreach ($children as $child) {
                if (! $child instanceof Node) {
                    continue;
                }

                $this->visit($child, $scope, $dispatches, $protected, $connections);
            }
        }
    }

    private function isDispatchCall(Node $node, Scope $scope): bool
    {
        if ($node instanceof FuncCall) {
            return $this->callHelper->matchingNames($node, $scope, 'dispatch') !== [];
        }

        if (! $node instanceof StaticCall) {
            return false;
        }

        return $this->callHelper->matchingNames($node, $scope, 'dispatch') !== []
            && ! $this->callHelper->isCalledOn($node, $scope, [Bus::class, Queue::class]);
    }

    private function dispatchInReceiverChain(MethodCall|NullsafeMethodCall $afterCommitCall, Scope $scope): Node|null
    {
        $current = $afterCommitCall;

        while ($current instanceof MethodCall || $current instanceof NullsafeMethodCall) {
            $receiver = $current->var;

            if ($this->isDispatchCall($receiver, $scope)) {
                return $receiver;
            }

            $current = $receiver;
        }

        return null;
    }

    private function dispatchedJobNeedingAfterCommit(
        Node $dispatch,
        Scope $scope,
        bool|null $afterCommit,
        string|null $connection,
    ): ClassReflection|null {
        foreach ($this->dispatchedJobReflections($dispatch, $scope) as $reflection) {
            if ($reflection->is(ShouldQueue::class) && ! $this->declaresAfterCommit($reflection, $afterCommit, $connection)) {
                return $reflection;
            }
        }

        return null;
    }

    /** @return list<ClassReflection> */
    private function dispatchedJobReflections(Node $dispatch, Scope $scope): array
    {
        if ($dispatch instanceof StaticCall) {
            return $this->callHelper->receiverType($dispatch, $scope)->getObjectClassReflections();
        }

        if ($dispatch instanceof FuncCall) {
            $job = $dispatch->getArg('job', 0)?->value;

            return $job === null ? [] : $scope->getType($job)->getObjectClassReflections();
        }

        return [];
    }

    private function declaresAfterCommit(ClassReflection $class, bool|null $afterCommit, string|null $connection): bool
    {
        $native = $class->getNativeReflection();

        $afterCommit ??= $native->hasProperty('afterCommit')
            ? $native->getProperty('afterCommit')->getDefaultValue()
            : null;

        if ($class->is(ShouldQueueAfterCommit::class)) {
            return $afterCommit !== false;
        }

        if (is_bool($afterCommit)) {
            return $afterCommit;
        }

        return $this->connectionDispatchesAfterCommit($connection ?? $this->jobConnection($class));
    }

    private function jobConnection(ClassReflection $class): string|null
    {
        $native = $class->getNativeReflection();

        if (! $native->hasProperty('connection')) {
            return null;
        }

        $value = $native->getProperty('connection')->getDefaultValue();

        return is_string($value) ? $value : null;
    }

    private function connectionDispatchesAfterCommit(string|null $connection): bool
    {
        $config = $this->config();

        if ($config === null) {
            return false;
        }

        $connection ??= $config->get('queue.default');

        if (! is_string($connection) || $connection === '') {
            return false;
        }

        return $this->afterCommitByConnection[$connection] ??= $config->get('queue.connections.' . $connection . '.after_commit') === true;
    }

    private function config(): Repository|null
    {
        if ($this->config !== false) {
            return $this->config;
        }

        $config = $this->containerHelper->resolve('config');

        return $this->config = $config instanceof Repository ? $config : null;
    }
}
