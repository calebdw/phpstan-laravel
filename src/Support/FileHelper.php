<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use PHPStan\Analyser\DependencyTracker;
use PHPStan\File\FileHelper as PHPStanFileHelper;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

use function glob;
use function is_dir;
use function iterator_to_array;

use const GLOB_ONLYDIR;

final class FileHelper
{
    public function __construct(
        private PHPStanFileHelper $fileHelper,
    ) {
    }

    /**
     * @param  array<array-key, string> $directories
     * @param  list<string>|string|null $name
     *
     * @return array<string, SplFileInfo>
     */
    public function getFiles(array $directories, array|string|null $name = null, bool $recursive = true): array
    {
        $resolvedDirectories = $this->getDirectories($directories);

        if ($resolvedDirectories === []) {
            return [];
        }

        $finder = Finder::create()->files()->in($resolvedDirectories);

        if ($name !== null) {
            $finder->name($name);
        }

        if (! $recursive) {
            $finder->depth(0);
        }

        return iterator_to_array($finder);
    }

    /**
     * The directories the given paths name, with globs expanded to the
     * directories that exist right now.
     *
     * @param  array<array-key, string> $directories
     *
     * @return list<string>
     */
    public function getDirectories(array $directories): array
    {
        /** @var list<string> $resolved */
        $resolved = [];

        foreach ($directories as $directory) {
            $directory = $this->fileHelper->absolutizePath($directory);

            if (is_dir($directory)) {
                $resolved[] = $directory;

                continue;
            }

            foreach (glob($directory, GLOB_ONLYDIR) ?: [] as $globbedDirectory) {
                $resolved[] = $globbedDirectory;
            }
        }

        return $resolved;
    }

    /**
     * Lets the result cache run the current rule again when a file matching
     * the pattern changes in one of the given directories. A directory that
     * does not exist yet is tracked too, so creating it counts as a change.
     *
     * @param  array<array-key, string> $directories
     */
    public function trackDirectories(DependencyTracker $tracker, array $directories, string $pattern = '*'): void
    {
        foreach ($directories as $directory) {
            $directory = $this->fileHelper->absolutizePath($directory);

            $resolved = is_dir($directory)
                ? [$directory]
                : (glob($directory, GLOB_ONLYDIR) ?: [$directory]);

            foreach ($resolved as $resolvedDirectory) {
                $tracker->trackDirectoryDependency($resolvedDirectory, $pattern);
            }
        }
    }
}
