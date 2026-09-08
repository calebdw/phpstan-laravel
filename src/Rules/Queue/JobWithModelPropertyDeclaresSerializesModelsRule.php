<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules\Queue;

use CalebDW\PhpstanLaravel\Support\QueuedJobHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;
use ReflectionProperty;

use function array_map;
use function implode;
use function sprintf;

/**
 * Without SerializesModels, a public Eloquent model is serialized whole onto
 * the queue and rehydrated from a stale dispatch-time snapshot.
 *
 * @implements Rule<InClassNode>
 */
class JobWithModelPropertyDeclaresSerializesModelsRule implements Rule
{
    public function __construct(
        private QueuedJobHelper $queuedJobHelper,
        private TypeHelper $typeHelper,
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

        if (! $this->queuedJobHelper->isConcrete($class, ShouldQueue::class)) {
            return [];
        }

        if ($class->hasTraitUse(SerializesModels::class)) {
            return [];
        }

        $modelProperties = $this->modelTypedPublicProperties($class);

        if ($modelProperties === []) {
            return [];
        }

        return [
            /** @phpstan-ignore method.internal (still experimental) */
            RuleErrorBuilder::message(sprintf(
                'Job %s has model properties (%s) but does not use SerializesModels.',
                $class->getDisplayName(),
                implode(', ', array_map(static fn (string $name): string => '$' . $name, $modelProperties)),
            ))
                ->tip('Use the Illuminate\\Queue\\SerializesModels trait.')
                ->identifier('laravel.job.missingSerializesModels')
                ->line($node->getStartLine())
                ->fixNode($node->getOriginalNode(), static function (Node $class): Node {
                    if (! $class instanceof Class_) {
                        return $class;
                    }

                    $class->stmts = [
                        new TraitUse([new FullyQualified(SerializesModels::class)]),
                        ...$class->stmts,
                    ];

                    return $class;
                })
                ->build(),
        ];
    }

    /** @return list<string> */
    private function modelTypedPublicProperties(ClassReflection $class): array
    {
        $names = [];

        foreach ($class->getNativeReflection()->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $type = $class->getNativeProperty($property->getName())->getReadableType();

            if (! $this->typeHelper->isCalledOn(TypeCombinator::removeNull($type), Model::class)) {
                continue;
            }

            $names[] = $property->getName();
        }

        return $names;
    }
}
