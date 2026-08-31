<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\PhpDoc;

use CalebDW\PhpstanLaravel\Support\FileHelper;
use PHPStan\PhpDoc\StubFilesExtension as StubFilesExtensionContract;
use SplFileInfo;

use function array_filter;
use function array_values;
use function basename;
use function glob;
use function realpath;
use function rtrim;
use function strlen;
use function substr;
use function usort;
use function version_compare;

use const DIRECTORY_SEPARATOR;
use const GLOB_ONLYDIR;

final class StubFilesExtension implements StubFilesExtensionContract
{
    public function __construct(
        private FileHelper $fileHelper,
        private string $stubDirectory = __DIR__ . '/../../stubs',
        private string|null $laravelVersion = null,
    ) {
    }

    /** @inheritDoc */
    public function getFiles(): array
    {
        $stubDirectories = glob($this->stubDirectory . '/[0-9]*', GLOB_ONLYDIR) ?: [];

        $stubDirectories = array_values(array_filter(
            $stubDirectories,
            fn (string $directory): bool => version_compare(
                basename($directory),
                $this->laravelVersion ?? LARAVEL_VERSION,
                '<=',
            ),
        ));

        usort(
            $stubDirectories,
            static fn (string $a, string $b): int => version_compare(basename($a), basename($b)),
        );

        $directories = [$this->stubDirectory . '/common', ...$stubDirectories];
        $files       = [];

        // Later version directories replace common or older stubs with the
        // same relative pathname while leaving unrelated stubs in place.
        foreach ($directories as $directory) {
            $absoluteDirectory = realpath($directory);

            if ($absoluteDirectory === false) {
                continue;
            }

            foreach ($this->fileHelper->getFiles([$absoluteDirectory], '/\.stub$/i') as $file) {
                $files[$this->relativePathname($file, $absoluteDirectory)] = $file->getPathname();
            }
        }

        return array_values($files);
    }

    private function relativePathname(SplFileInfo $file, string $directory): string
    {
        return substr($file->getPathname(), strlen(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR));
    }
}
