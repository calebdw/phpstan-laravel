<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Closure;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\DependencyInjection\AutowiredService;

use function array_keys;
use function array_pop;

#[AutowiredService]
final class DependencyHelper
{
    /** @var list<array<string, Closure(DependencyTracker): void>> */
    private array $collectors = [];

    public function trackFile(DependencyTracker $tracker, string $file): void
    {
        $this->track($tracker, 'file:' . $file, static fn (DependencyTracker $t) => $t->trackFileDependency($file));
    }

    public function trackDirectory(DependencyTracker $tracker, string $directory, string $pattern = '*'): void
    {
        $this->track($tracker, 'directory:' . $directory . ':' . $pattern, static fn (DependencyTracker $t) => $t->trackDirectoryDependency($directory, $pattern));
    }

    /**
     * @param callable(): T $callback
     *
     * @return array{T, array<string, Closure(DependencyTracker): void>}
     *
     * @template T
     */
    public function capture(callable $callback): array
    {
        $this->collectors[] = [];

        try {
            $result = $callback();
        } finally {
            $dependencies = array_pop($this->collectors);
        }

        return [$result, $dependencies];
    }

    /** @param array<string, Closure(DependencyTracker): void> $dependencies */
    public function replay(DependencyTracker $tracker, array $dependencies): void
    {
        foreach ($dependencies as $key => $dependency) {
            $this->track($tracker, $key, $dependency);
        }
    }

    /** @param Closure(DependencyTracker): void $dependency */
    private function track(DependencyTracker $tracker, string $key, Closure $dependency): void
    {
        $dependency($tracker);

        foreach (array_keys($this->collectors) as $index) {
            $this->collectors[$index][$key] = $dependency;
        }
    }
}
