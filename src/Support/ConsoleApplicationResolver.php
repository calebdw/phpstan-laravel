<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use PHPStan\Reflection\ClassReflection;

use function app;
use function collect;
use function is_a;

final class ConsoleApplicationResolver
{
    /** @var array<string, Command>|null */
    private array|null $commands = null;

    /** @var array<class-string, array<string, Command>> */
    private array $commandsByClass = [];

    /** @return array<string, Command> */
    public function findCommands(ClassReflection $classReflection): array
    {
        if (! $classReflection->is(Command::class)) {
            return [];
        }

        $className = $classReflection->getName();

        return $this->commandsByClass[$className] ??= collect($this->commands())
            ->filter(static fn ($c) => is_a($c, $className, true))
            ->all();
    }

    /** @return array<string, Command> */
    private function commands(): array
    {
        return $this->commands ??= collect(app(Kernel::class)->all())
            ->filter(static fn ($c) => $c instanceof Command)
            ->all();
    }
}
