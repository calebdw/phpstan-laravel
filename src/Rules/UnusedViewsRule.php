<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Collectors\UsedViewCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedViewInAnotherViewCollector;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use CalebDW\PhpstanLaravel\Support\ViewFileHelper;
use Illuminate\View\Factory;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function collect;

/** @implements Rule<CollectedDataNode> */
final class UnusedViewsRule implements Rule
{
    public function __construct(
        private ViewFileHelper $viewFileHelper,
        private UsedViewInAnotherViewCollector $usedViewInAnotherViewCollector,
        private ContainerHelper $containerHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /** @return RuleError[] */
    public function processNode(Node $node, Scope $scope): array
    {
        $factory = $this->containerHelper->resolve(Factory::class);
        $finder  = $factory->getFinder();

        $usedViews = collect($node->get(UsedViewCollector::class))
            ->flatten()
            ->concat($this->usedViewInAnotherViewCollector->getUsedViews());

        return collect($this->viewFileHelper->getAllViewNames())
            ->diff($usedViews)
            ->unique()
            ->filter($factory->exists(...))
            ->map(static fn (string $view) => RuleErrorBuilder::message('This view is not used in the project.')
                ->file($finder->find($view))
                ->line(0)
                ->identifier('laravel.unusedView')
                ->build())
            ->all();
    }
}
