<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules\Queue;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\QueuedJobHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function implode;
use function sprintf;

/**
 * Jobs added to Bus::batch() need Batchable so the batch can track them and
 * so $this->batch() exists on the job.
 *
 * @implements Rule<StaticCall>
 */
final class BatchedJobIsBatchableRule implements Rule
{
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
        if ($this->callHelper->matchingNames($node, $scope, 'batch') === []) {
            return [];
        }

        $jobs = $node->getArg('jobs', 0)?->value;

        if (! $jobs instanceof Array_ || ! $this->callHelper->isCalledOn($node, $scope, Bus::class)) {
            return [];
        }

        $errors = [];

        foreach ($this->queuedJobHelper->jobItems($jobs, $scope) as $item) {
            $names = $this->typeHelper->classNames(
                $item['type'],
                static fn ($c) => $c->is(ShouldQueue::class) && ! $c->hasTraitUse(Batchable::class),
            );

            if ($names === []) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Job %s is batched but does not use Batchable.',
                implode('|', $names),
            ))
                ->tip('Use the Illuminate\\Bus\\Batchable trait.')
                ->identifier('laravel.batchedJob.missingBatchable')
                ->line($item['line'])
                ->build();
        }

        return $errors;
    }
}
