<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules\Queue;

use CalebDW\PhpstanLaravel\Support\QueuedJobHelper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function collect;
use function sprintf;

/**
 * Laravel keys the uniqueness lock as class + uniqueId, falling back to an
 * empty uniqueId. A parameterized job without uniqueId collapses every
 * dispatch into one lock.
 *
 * @implements Rule<InClassNode>
 */
class UniqueJobDeclaresUniqueIdRule implements Rule
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

        if ($class->hasNativeMethod('uniqueId') || $class->hasNativeProperty('uniqueId')) {
            return [];
        }

        if (! $this->hasConstructorParameters($class)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Unique job %s has constructor parameters but does not declare uniqueId.',
                $class->getDisplayName(),
            ))
                ->tip('Declare a uniqueId() method or a $uniqueId property to identify distinct jobs.')
                ->identifier('laravel.uniqueJob.missingUniqueId')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function hasConstructorParameters(ClassReflection $class): bool
    {
        if (! $class->hasConstructor()) {
            return false;
        }

        return collect($class->getConstructor()->getVariants())
            ->contains(static fn ($v) => $v->getParameters() !== []);
    }
}
