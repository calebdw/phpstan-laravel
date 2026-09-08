<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules\Queue;

use CalebDW\PhpstanLaravel\Support\QueuedJobHelper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;

/**
 * Without uniqueFor, Laravel holds the uniqueness lock until the job finishes.
 * A worker that dies mid-job never releases it.
 *
 * @implements Rule<InClassNode>
 */
class UniqueJobDeclaresUniqueForRule implements Rule
{
    public function __construct(private QueuedJobHelper $queuedJobHelper)
    {
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

        if (! $this->queuedJobHelper->isConcrete($class, ShouldBeUnique::class)) {
            return [];
        }

        if ($class->hasNativeProperty('uniqueFor') || $class->hasNativeMethod('uniqueFor')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Job %s implements ShouldBeUnique but does not declare uniqueFor.',
                $class->getDisplayName(),
            ))
                ->tip('Declare a $uniqueFor property or a uniqueFor() method.')
                ->identifier('laravel.uniqueJob.missingUniqueFor')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
