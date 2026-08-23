<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Concerns\HasContainer;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\File\FileHelper;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function count;
use function is_dir;
use function is_string;
use function str_starts_with;

/**
 * Catches `env()` calls outside of the config directory.
 *
 * @implements Rule<FuncCall>
 */
class NoEnvCallsOutsideOfConfigRule implements Rule
{
    use HasContainer;

    /** @var list<string> */
    private array $configDirectories = [];

    /** @param  list<non-empty-string> $configDirectories */
    public function __construct(array $configDirectories, private FileHelper $fileHelper)
    {
        if (count($configDirectories) !== 0) {
            foreach ($configDirectories as $directory) {
                $this->configDirectories[] = $this->fileHelper->normalizePath($directory);
            }

            return;
        }

        // Resolved through the container rather than config_path(), which is
        // a Foundation helper and is not necessarily loaded when a package is
        // being analysed on its own.
        $path = $this->resolve('path.config');

        if (! is_string($path)) {
            return;
        }

        $this->configDirectories = [$path];
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @return array<int, RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $name = $node->name;

        if (! $name instanceof Name) {
            return [];
        }

        if ($scope->resolveName($name) !== 'env') {
            return [];
        }

        if (! $this->isCalledOutsideOfConfig($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Called 'env' outside of the config directory which returns null when the config is cached, use 'config'.")
                ->identifier('laravel.noEnvCallsOutsideOfConfig')
                ->line($node->getStartLine())
                ->file($scope->getFile(), $scope->getFileDescription())
                ->build(),
        ];
    }

    protected function isCalledOutsideOfConfig(FuncCall $call, Scope $scope): bool
    {
        // With no config directory to compare against there is no way to tell
        // inside from outside, and reporting every call would be worse than
        // reporting none.
        if ($this->configDirectories === []) {
            return false;
        }

        foreach ($this->configDirectories as $configDirectory) {
            $absolutePath = $this->fileHelper->absolutizePath($configDirectory);

            if (! is_dir($absolutePath)) {
                continue;
            }

            if (str_starts_with($scope->getFile(), $absolutePath)) {
                return false;
            }
        }

        return true;
    }
}
