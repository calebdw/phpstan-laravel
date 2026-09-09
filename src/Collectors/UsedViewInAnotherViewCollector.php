<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Collectors;

use CalebDW\PhpstanLaravel\Support\ViewFileHelper;
use CalebDW\PhpstanLaravel\Support\ViewParser;
use PhpParser\Node;

use function array_merge;
use function preg_match_all;
use function str_contains;

use const PREG_SET_ORDER;

final class UsedViewInAnotherViewCollector
{
    /** @see https://regex101.com/r/OyHHCY/1 */
    private const string VIEW_NAME_REGEX = '/@(extends|include(If|Unless|When|First)?)(\(.*?([\'"])(.*?)([\'"])([),]))/m';

    /** Anonymous Blade tags: `<x-alert>`, `<x-forms.input />`, `</x-card>`. */
    private const string COMPONENT_TAG_REGEX = '/<\/?x-([a-zA-Z0-9][a-zA-Z0-9._-]*)/';

    public function __construct(private ViewParser $viewParser, private ViewFileHelper $viewFileHelper)
    {
    }

    /** @return list<string> */
    public function getUsedViews(): array
    {
        $usedViews = [];

        foreach ($this->viewFileHelper->getAllViewFilePaths() as $viewFile) {
            $parserNodes = $this->viewParser->getNodes($viewFile);

            $usedViews = array_merge($usedViews, $this->processNodes($parserNodes));
        }

        return $usedViews;
    }

    /**
     * @param  Node\Stmt[] $nodes
     *
     * @return list<string>
     */
    private function processNodes(array $nodes): array
    {
        $usedViews = [];

        foreach ($nodes as $node) {
            if (! $node instanceof Node\Stmt\InlineHTML) {
                continue;
            }

            preg_match_all(self::VIEW_NAME_REGEX, $node->value, $matches, PREG_SET_ORDER, 0);

            foreach ($matches as $match) {
                $usedViews[] = $match[5];
            }

            if (! str_contains($node->value, '<x-') && ! str_contains($node->value, '</x-')) {
                continue;
            }

            foreach ($this->componentViews($node->value) as $view) {
                $usedViews[] = $view;
            }
        }

        return $usedViews;
    }

    /** @return list<string> */
    private function componentViews(string $html): array
    {
        preg_match_all(self::COMPONENT_TAG_REGEX, $html, $matches, PREG_SET_ORDER, 0);

        $usedViews = [];

        foreach ($matches as $match) {
            $name = $match[1];

            if ($name === 'slot' || $name === 'dynamic-component') {
                continue;
            }

            // `resources/views/components` is often its own view path (`alert`)
            // as well as a nested path (`components.alert`). Index files add
            // another form (`alert/index.blade.php`). Emit every naming.
            foreach ([$name, 'components.' . $name] as $view) {
                $usedViews[] = $view;
                $usedViews[] = $view . '.index';
            }
        }

        return $usedViews;
    }
}
