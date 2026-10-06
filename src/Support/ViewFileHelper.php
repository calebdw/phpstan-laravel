<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Generator;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewFinderInterface;
use SplFileInfo;

use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function rtrim;
use function str_contains;
use function str_replace;
use function strpos;

use const DIRECTORY_SEPARATOR;

final class ViewFileHelper
{
    /** @var list<string>|null */
    private array|null $directories = null;

    /** @param  list<non-empty-string> $viewDirectories */
    public function __construct(
        private array $viewDirectories,
        private FileHelper $fileHelper,
        private ContainerHelper $containerHelper,
    ) {
    }

    /**
     * The directories a view can live in: the configured ones, or the view
     * finder's own paths and namespace hints.
     *
     * Resolved on demand rather than in the constructor. The finder is only
     * there once the application is booted, which does not happen in the
     * process that reads the result cache, so a service constructed there
     * would take the whole run down with it.
     *
     * @return list<string>
     */
    public function getViewDirectories(): array
    {
        if ($this->directories !== null) {
            return $this->directories;
        }

        if ($this->viewDirectories !== []) {
            return $this->directories = $this->viewDirectories;
        }

        $finder = $this->finder();

        if ($finder === null) {
            return $this->directories = [];
        }

        return $this->directories = array_values(array_unique(array_merge(
            $finder->getPaths(),
            ...array_values($finder->getHints()),
        )));
    }

    /**
     * The files the named view could be loaded from, in the order the finder
     * would try them, whether or not any of them exists.
     *
     * The name is expected to be normalised, as ViewName::normalize() leaves
     * it: dots for separators, with a `pkg::` namespace kept intact.
     *
     * Always the finder's own paths, never the configured view directories:
     * `view-string` asks the finder whether a view exists, so this has to
     * look where the answer comes from.
     *
     * @see FileViewFinder::findInPaths()
     *
     * @return list<string>
     */
    public function getViewFilePaths(string $view): array
    {
        $finder = $this->finder();

        if ($finder === null) {
            return [];
        }

        $paths = $finder->getPaths();

        // A delimiter at the very start is not a namespace, the same way the
        // finder reads it.
        if (strpos($view, ViewFinderInterface::HINT_PATH_DELIMITER) > 0) {
            [$namespace, $view] = explode(ViewFinderInterface::HINT_PATH_DELIMITER, $view, 2);

            $paths = $finder->getHints()[$namespace] ?? [];
        }

        $relative = str_replace('.', '/', $view);
        $files    = [];

        foreach ($paths as $path) {
            foreach ($finder->getExtensions() as $extension) {
                $files[] = rtrim($path, '/\\') . '/' . $relative . '.' . $extension;
            }
        }

        return $files;
    }

    /** @return Generator<int, string, void, void> */
    public function getRootViewFilePaths(): Generator
    {
        $finder = $this->finder();

        if ($finder === null) {
            return;
        }

        foreach ($finder->getPaths() as $path) {
            foreach ($this->getViews($path) as $view) {
                yield $view->getPathname();
            }
        }
    }

    /** @return Generator<int, string, void, void> */
    public function getAllViewFilePaths(): Generator
    {
        foreach ($this->getViewDirectories() as $viewDirectory) {
            foreach ($this->getViews($viewDirectory) as $view) {
                yield $view->getPathname();
            }
        }
    }

    /** @return Generator<int, string, void, void> */
    public function getAllViewNames(): Generator
    {
        foreach ($this->getViewDirectories() as $viewDirectory) {
            foreach ($this->getViews($viewDirectory) as $view) {
                if (str_contains($view->getPathname(), 'views' . DIRECTORY_SEPARATOR . 'vendor') || str_contains($view->getPathname(), 'views' . DIRECTORY_SEPARATOR . 'errors')) {
                    continue;
                }

                $viewName = explode(rtrim($viewDirectory, '/\\') . DIRECTORY_SEPARATOR, $view->getPathname());

                yield str_replace([DIRECTORY_SEPARATOR, '.blade.php'], ['.', ''], $viewName[1]);
            }
        }
    }

    /**
     * The paths live on the concrete finder, which the concrete factory is
     * what hands out, and neither is what the contracts declare. Anything
     * else - including nothing at all, outside a booted application - leaves
     * the view directories unknown rather than failing the run.
     */
    private function finder(): FileViewFinder|null
    {
        $factory = $this->containerHelper->resolve(ViewFactoryContract::class);

        if (! $factory instanceof ViewFactory) {
            return null;
        }

        $finder = $factory->getFinder();

        return $finder instanceof FileViewFinder ? $finder : null;
    }

    /** @return SplFileInfo[] */
    private function getViews(string $path): array
    {
        return $this->fileHelper->getFiles([$path], '/\.blade\.php$/i');
    }
}
