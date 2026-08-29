<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Internal;

use PHPStan\File\FileHelper as PHPStanFileHelper;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

use function glob;
use function is_dir;
use function iterator_to_array;

use const GLOB_ONLYDIR;

/** @internal */
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
        /** @var list<string> $resolvedDirectories */
        $resolvedDirectories = [];

        foreach ($directories as $directory) {
            $directory = $this->fileHelper->absolutizePath($directory);

            if (is_dir($directory)) {
                $resolvedDirectories[] = $directory;

                continue;
            }

            foreach (glob($directory, GLOB_ONLYDIR) ?: [] as $globbedDirectory) {
                $resolvedDirectories[] = $globbedDirectory;
            }
        }

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
}
