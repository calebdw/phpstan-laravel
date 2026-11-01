<?php

declare(strict_types=1);

namespace Tests\Unit;

use CalebDW\PhpstanLaravel\Support\FileHelper;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\File\FileHelper as PHPStanFileHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;

class FileHelperTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function directories(): iterable
    {
        yield 'existing directory' => ['tests/Unit', 'tests/Unit'];
        yield 'missing directory' => ['tests/missing-translations', 'tests/missing-translations'];
        yield 'existing glob matches' => ['tests/*', 'tests'];
        yield 'missing glob matches' => ['tests/missing-*/views', 'tests'];
        yield 'nested globs' => ['tests/*/views/*', 'tests'];
        yield 'question mark glob' => ['tests/missing-?/views', 'tests'];
        yield 'character class glob' => ['tests/missing-[ab]/views', 'tests'];
    }

    #[DataProvider('directories')]
    public function testTracksDirectories(string $directory, string $expected): void
    {
        $root    = dirname(__DIR__, 2);
        $tracker = $this->createMock(DependencyTracker::class);
        $tracker->expects(self::once())
            ->method('trackDirectoryDependency')
            ->with($root . '/' . $expected, '*.php');

        $helper = new FileHelper(new PHPStanFileHelper($root));
        $helper->trackDirectories($tracker, [$directory], '*.php');
    }
}
