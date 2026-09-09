<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules\Queue;

use CalebDW\PhpstanLaravel\Support\QueuedJobHelper;
use CalebDW\PhpstanLaravel\Support\ReflectionHelper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;
use function version_compare;

/**
 * Without uniqueFor, Laravel holds the uniqueness lock until the job finishes.
 * A worker that dies mid-job never releases it.
 *
 * @implements Rule<InClassNode>
 */
final class UniqueJobDeclaresUniqueForRule implements Rule
{
    private const string UNIQUE_FOR = 'Illuminate\Queue\Attributes\UniqueFor';

    public function __construct(
        private QueuedJobHelper $queuedJobHelper,
        private ReflectionHelper $reflectionHelper,
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

        if (! $this->queuedJobHelper->isConcrete($class, ShouldBeUnique::class)) {
            return [];
        }

        if ($class->hasNativeProperty('uniqueFor') || $class->hasNativeMethod('uniqueFor')) {
            return [];
        }

        $supportsUniqueForAttribute = $this->laravelAtLeast('13.0.0');

        if (
            $supportsUniqueForAttribute
            && $this->reflectionHelper->hasAttribute($class, self::UNIQUE_FOR)
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Job %s implements ShouldBeUnique but does not declare uniqueFor.',
                $class->getDisplayName(),
            ))
                ->tip($supportsUniqueForAttribute
                    ? 'Declare a $uniqueFor property, a uniqueFor() method, or a UniqueFor attribute.'
                    : 'Declare a $uniqueFor property or a uniqueFor() method.')
                ->identifier('laravel.uniqueJob.missingUniqueFor')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function laravelAtLeast(string $version): bool
    {
        return version_compare(LARAVEL_VERSION, $version, '>=');
    }
}
