<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Internal;

use Illuminate\Console\Application;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ObjectType;

use function app;

/** @internal */
#[AutowiredService]
final class ConsoleApplicationResolver
{
    private Application|null $application = null;

    /** @return Command[] */
    public function findCommands(ClassReflection $classReflection): array
    {
        $consoleApplication = $this->getApplication();

        $classType = new ObjectType($classReflection->getName());

        if (! (new ObjectType('Illuminate\Console\Command'))->isSuperTypeOf($classType)->yes()) {
            return [];
        }

        $commands = [];

        foreach ($consoleApplication->all() as $name => $command) {
            if (! $classType->isSuperTypeOf(new ObjectType($command::class))->yes()) {
                continue;
            }

            $commands[$name] = $command;
        }

        /** @phpstan-ignore return.type (the console application does not type its command list) */
        return $commands;
    }

    private function getApplication(): Application
    {
        if ($this->application === null) {
            $this->application = new Application(app(Container::class), app(Dispatcher::class), app()->version());
            $this->application->setContainerCommandLoader();
        }

        return $this->application;
    }
}
