<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Collectors\UsedViewCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedViewInAnotherViewCollector;
use CalebDW\PhpstanLaravel\Support\ViewFileHelper;
use Illuminate\View\Factory;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function array_diff;
use function array_filter;
use function array_unique;
use function collect;
use function iterator_to_array;
use function view;

/** @implements Rule<CollectedDataNode> */
final class UnusedViewsRule implements Rule
{
    /** @var list<string>|null */
    private array|null $viewsUsedInOtherViews = null;

    public function __construct(private UsedViewInAnotherViewCollector $usedViewInAnotherViewCollector, private ViewFileHelper $viewFileHelper)
    {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /** @return RuleError[] */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->viewsUsedInOtherViews === null) {
            $this->viewsUsedInOtherViews = $this->usedViewInAnotherViewCollector->getUsedViews();
        }

        $usedViews = collect([
            $node->get(UsedViewCollector::class),
            $this->viewsUsedInOtherViews,
        ])->flatten()->unique()->toArray();

        /** @var Factory $factory */
        $factory = view();
        $finder  = $factory->getFinder();

        $allViews = iterator_to_array($this->viewFileHelper->getAllViewNames());

        $usedViews = static::filterExistingViews($factory, $usedViews);
        $allViews  = static::filterExistingViews($factory, $allViews);

        $unusedViews = array_unique(array_diff($allViews, $usedViews));

        $errors = [];
        foreach ($unusedViews as $file) {
            $path = $finder->find($file);

            $errors[] = RuleErrorBuilder::message('This view is not used in the project.')
                ->file($path)
                ->line(0)
                ->identifier('laravel.unusedView')
                ->build();
        }

        return $errors;
    }

    /**
     * @param string[] $views
     *
     * @return string[]
     */
    protected static function filterExistingViews(Factory $factory, array $views): array
    {
        return array_filter($views, static function (string $view) use ($factory): bool {
            return $factory->exists($view);
        });
    }
}
