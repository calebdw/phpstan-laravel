<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Collectors\UsedTranslationFacadeCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedTranslationFunctionCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedTranslationTranslatorCollector;
use CalebDW\PhpstanLaravel\Collectors\UsedTranslationViewCollector;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use Illuminate\Support\Str;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use SplFileInfo;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function explode;
use function file_get_contents;
use function in_array;
use function is_array;
use function json_decode;
use function lang_path;
use function rtrim;
use function str_contains;
use function strlen;
use function strval;

use const DIRECTORY_SEPARATOR;

/** @implements Rule<CollectedDataNode> */
final class NoMissingTranslationsRule implements Rule
{
    /** @param string[] $translationDirectories */
    public function __construct(
        private UsedTranslationViewCollector $usedTranslationViewCollector,
        private FileHelper $fileHelper,
        private array $translationDirectories,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /** @return RuleError[] */
    public function processNode(Node $node, Scope $scope): array
    {
        $paths = $this->translationDirectories ?: [lang_path()];

        /** @var array<string, array{0: string, 1: int}[]>[] $collectors */
        $collectors = [
            $node->get(UsedTranslationFunctionCollector::class),
            $node->get(UsedTranslationTranslatorCollector::class),
            $node->get(UsedTranslationFacadeCollector::class),
            $this->usedTranslationViewCollector->getUsedTranslations(),
        ];

        $availableTranslations = [];

        foreach ($paths as $path) {
            $files = $this->fileHelper->getFiles([$path], '/\.(php|json)$/i');

            // getFiles() keys by pathname, and array_merge() reads string keys
            // in a spread as named arguments.
            $translations = array_values(array_map(
                fn (SplFileInfo $file): array => $this->translations($file, $path),
                $files,
            ));

            $availableTranslations = array_merge($availableTranslations, ...$translations);
        }

        $usedTranslations = [];

        foreach ($collectors as $collector) {
            foreach ($collector as $file => $translations) {
                if (! array_key_exists($file, $usedTranslations)) {
                    $usedTranslations[$file] = [];
                }

                $usedTranslations[$file] = array_merge($usedTranslations[$file], $translations);
            }
        }

        $errors = [];

        foreach ($usedTranslations as $file => $translations) {
            foreach ($translations as [$translation, $line]) {
                if (in_array($translation, $availableTranslations, true) || str_contains($translation, '::')) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message('Translation "' . $translation . '" has not been found.')
                    ->file($file)
                    ->line($line)
                    ->identifier('laravel.missingTranslation')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * A JSON file holds its keys directly, while a PHP file is addressed by the
     * path that leads to it, without the locale directory it sits under.
     *
     * @return string[]
     */
    private function translations(SplFileInfo $file, string $directory): array
    {
        if ($file->getExtension() === 'json') {
            $contents = file_get_contents($file->getPathname());
            $decoded  = $contents === false ? null : json_decode($contents, true);

            if (! is_array($decoded)) {
                return [];
            }

            return array_map(strval(...), array_keys($decoded));
        }

        $prefix = Str::of($this->relativePathname($file, $directory))
            ->explode(DIRECTORY_SEPARATOR)
            ->slice(1, -1) // Trim locale and filename
            ->join('/');

        $filename = $file->getBasename('.' . $file->getExtension());

        $root = strlen($prefix) > 0
            ? $prefix . '/' . $filename
            : $filename;

        $array = (static fn (): mixed => require $file->getPathname())();

        if (! is_array($array)) {
            return [];
        }

        return array_merge([$root], $this->keys($array, $root));
    }

    private function relativePathname(SplFileInfo $file, string $directory): string
    {
        $parts = explode(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR, $file->getPathname());

        return $parts[1] ?? $file->getBasename();
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return string[]
     */
    protected function keys(array $array, string $prefix): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix . '.' . $key;

            $results[] = $newKey;

            if (! is_array($value)) {
                continue;
            }

            $results = array_merge($results, $this->keys($value, $newKey));
        }

        return $results;
    }
}
