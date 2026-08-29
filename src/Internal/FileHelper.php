<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Internal;

use PHPStan\File\FileHelper as PHPStanFileHelper;
use SplFileInfo;

use function array_reduce;
use function closedir;
use function glob;
use function is_dir;
use function is_link;
use function opendir;
use function preg_match;
use function readdir;
use function rtrim;

use const DIRECTORY_SEPARATOR;
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
     *
     * @return array<string, SplFileInfo>
     */
    public function getFiles(array $directories, string|null $filter = null, bool $recursive = true): array
    {
        return array_reduce(
            $directories,
            function (array $carry, string $path) use ($filter, $recursive): array {
                $absolutePath = $this->fileHelper->absolutizePath($path);

                if ($this->isGlobPattern($absolutePath)) {
                    $glob = glob($absolutePath, GLOB_ONLYDIR);

                    if ($glob === false) {
                        return $carry;
                    }

                    $directories = $glob;
                } else {
                    if (! is_dir($absolutePath)) {
                        return $carry;
                    }

                    $directories = [$absolutePath];
                }

                foreach ($directories as $directory) {
                    $carry += $this->scanDirectory($directory, $filter, $recursive);
                }

                return $carry;
            },
            [],
        );
    }

    /**
     * Directories are walked with readdir() rather than the SPL directory
     * iterators, which rewind a partially read directory handle. Filesystems
     * that silently ignore such a rewind — notably the 9p mounts used by WSL2
     * and Docker Desktop — drop the entries already buffered, hiding files
     * from the scan. Symlinked directories are not followed, matching the
     * behavior of the replaced iterators.
     *
     * @return array<string, SplFileInfo>
     */
    private function scanDirectory(string $directory, string|null $filter, bool $recursive): array
    {
        $handle = @opendir($directory);

        if ($handle === false) {
            return [];
        }

        $directory      = rtrim($directory, '/\\');
        $files          = [];
        $subdirectories = [];

        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                if ($recursive && ! is_link($path)) {
                    $subdirectories[] = $path;
                }

                continue;
            }

            if ($filter !== null && preg_match($filter, $path) !== 1) {
                continue;
            }

            $files[$path] = new SplFileInfo($path);
        }

        closedir($handle);

        foreach ($subdirectories as $subdirectory) {
            $files += $this->scanDirectory($subdirectory, $filter, $recursive);
        }

        return $files;
    }

    private function isGlobPattern(string $path): bool
    {
        return preg_match('~[*?[\]]~', $path) > 0;
    }
}
