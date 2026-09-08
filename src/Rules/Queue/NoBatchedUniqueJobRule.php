<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules\Queue;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\QueuedJobHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

use function implode;
use function sprintf;

/**
 * Bulk and batch dispatch bypass the uniqueness lock, so ShouldBeUnique
 * jobs must be dispatched individually.
 *
 * @implements Rule<StaticCall>
 */
class NoBatchedUniqueJobRule implements Rule
{
    private const array BULK_METHODS = ['batch', 'bulk'];

    public function __construct(
        private CallHelper $callHelper,
        private QueuedJobHelper $queuedJobHelper,
        private TypeHelper $typeHelper,
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
        $methods = $this->callHelper->matchingNames($node, $scope, self::BULK_METHODS);

        if ($methods === []) {
            return [];
        }

        $jobs = $node->getArg('jobs', 0)?->value;

        if ($jobs === null || ! $this->callHelper->isCalledOn($node, $scope, [Bus::class, Queue::class])) {
            return [];
        }

        $method = $methods[0];
        $errors = [];

        foreach ($this->items($jobs, $node, $scope) as $item) {
            $names = $this->uniqueJobNames($item['type']);

            if ($names === []) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Unique job %s is dispatched via %s().',
                implode('|', $names),
                $method,
            ))
                ->tip('Dispatch unique jobs individually to preserve uniqueness.')
                ->identifier('laravel.uniqueJob.batched')
                ->line($item['line'])
                ->build();
        }

        return $errors;
    }

    /** @return list<array{type: Type, line: int}> */
    private function items(Expr $jobs, StaticCall $node, Scope $scope): array
    {
        if ($jobs instanceof Array_) {
            return $this->queuedJobHelper->jobItems($jobs, $scope);
        }

        $type = $scope->getType($jobs);

        if (! $type->isIterable()->yes()) {
            return [];
        }

        return [['type' => $type->getIterableValueType(), 'line' => $node->getStartLine()]];
    }

    /** @return list<string> */
    private function uniqueJobNames(Type $type): array
    {
        $names = $this->typeHelper->classNames(
            $type,
            fn ($c) => $this->queuedJobHelper->isConcrete($c, ShouldBeUnique::class),
        );

        if ($names !== []) {
            return $names;
        }

        return $this->typeHelper->isCalledOn($type, ShouldBeUnique::class) ? ['dispatched here'] : [];
    }
}
