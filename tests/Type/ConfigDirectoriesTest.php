<?php

declare(strict_types=1);

namespace Type;

use CalebDW\PhpstanLaravel\Support\ConfigParser;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ConfigDirectoriesTest extends TypeInferenceTestCase
{
    public function testCachedValuesReplayTransitiveDependencies(): void
    {
        $parser      = self::getContainer()->getByType(ConfigParser::class);
        $scope       = $this->createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        $directories = [];
        $scope->method('trackDirectoryDependency')->willReturnCallback(static function (string $directory, string $pattern) use (&$directories): void {
            $directories[] = [$directory, $pattern];
        });

        $parser->getType('dependent.local', $scope);
        $directories = [];
        $parser->getType('dependent.another', $scope);
        self::assertContains([__DIR__ . '/data/config', 'scoped.php'], $directories);

        $directories = [];
        $parser->getType('dependent.local', $scope);
        self::assertContains([__DIR__ . '/data/config', 'scoped.php'], $directories);
    }

    /** @return iterable<mixed> */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/data/config-directories.php');
    }

    #[DataProvider('dataFileAsserts')]
    public function testFileAsserts(
        string $assertType,
        string $file,
        mixed ...$args,
    ): void {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-directories.neon'];
    }
}
