<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Console\Application;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use PHPStan\Reflection\ClassReflection;

use function app;
use function is_a;

final class ConsoleApplicationResolver
{
    private Application|null $application = null;

    /** @var array<string, Command[]> */
    private array $commandsByClass = [];

    /** @return Command[] */
    public function findCommands(ClassReflection $classReflection): array
    {
        if (! $classReflection->is(Command::class)) {
            return [];
        }

        $className = $classReflection->getName();

        return $this->commandsByClass[$className] ??= $this->resolveCommands($className);
    }

    /** @return Command[] */
    private function resolveCommands(string $className): array
    {
        $commands = [];

        foreach ($this->getApplication()->all() as $name => $command) {
            if (! $command instanceof Command || ! is_a($command, $className)) {
                continue;
            }

            $commands[$name] = $command;
        }

        return $commands;
    }

    private function getApplication(): Application
    {
        return $this->application ??= new Application(app(Container::class), app(Dispatcher::class), app()->version())
            ->setContainerCommandLoader();
    }
}
