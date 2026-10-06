<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\User;
use CalebDW\PhpstanLaravel\Schema\SchemaDependencyTracker;
use PHPStan\Analyser\DeclarationDependencyTracker;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\Concerns\HasDatabaseHelper;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

class SchemaDependencyTrackerTest extends PHPStanTestCase
{
    use HasDatabaseHelper;

    private string $migrations;

    private string $dumps;

    private DeclarationDependencyTracker $spy;

    public function setUp(): void
    {
        $this->setUpHasDatabaseHelper();

        $directory        = sys_get_temp_dir() . '/phpstan-laravel-deps-' . bin2hex(random_bytes(8));
        $this->migrations = $directory . '/migrations';
        $this->dumps      = $directory . '/schema';

        mkdir($this->migrations, recursive: true);
        mkdir($this->dumps, recursive: true);

        $this->spy = new class implements DeclarationDependencyTracker {
            /** @var list<array{string, string, string}> */
            public array $declared = [];

            public function trackDirectoryDependency(ClassReflection $classReflection, string $directory, string $pattern = '*'): void
            {
                $this->declared[] = [$classReflection->getName(), $directory, $pattern];
            }

            public function trackValueDependency(ClassReflection $classReflection, string $extensionClass, string $key): void
            {
            }

            public function trackFileDependency(ClassReflection $classReflection, string $file): void
            {
            }

            public function trackClassDependency(ClassReflection $classReflection, string $className): void
            {
            }
        };
    }

    #[Test]
    public function it_declares_every_directory_the_schema_is_parsed_from(): void
    {
        $this->tracker()->trackModel($this->reflection(User::class));

        self::assertSame(
            [
                [User::class, $this->migrations, '*'],
                [User::class, $this->dumps, '*'],
            ],
            $this->spy->declared,
        );
    }

    #[Test]
    public function it_declares_nothing_when_scanning_is_off(): void
    {
        $this->tracker(scan: false)->trackModel($this->reflection(User::class));

        self::assertSame([], $this->spy->declared);
    }

    #[Test]
    public function it_resolves_the_directories_once(): void
    {
        $tracker = $this->tracker();

        $tracker->trackModel($this->reflection(User::class));
        $tracker->trackModel($this->reflection(User::class));

        self::assertCount(4, $this->spy->declared);
    }

    private function tracker(bool $scan = true): SchemaDependencyTracker
    {
        return new SchemaDependencyTracker(
            $this->spy,
            $this->getMigrationHelper([$this->migrations], scan: $scan),
            $this->getSquashedMigrationHelper([$this->dumps], scan: $scan),
        );
    }

    /** @param class-string $class */
    private function reflection(string $class): ClassReflection
    {
        return self::createReflectionProvider()->getClass($class);
    }
}
