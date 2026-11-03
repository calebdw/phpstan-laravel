<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use CalebDW\PhpstanLaravel\Support\DependencyHelper;
use PHPStan\Analyser\DependencyTracker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DependencyHelperTest extends TestCase
{
    public function testNestedCapturesAndReplaysPreserveDependencies(): void
    {
        $helper = new DependencyHelper();
        $source = $this->createStub(DependencyTracker::class);

        [$value, $dependencies] = $helper->capture(static function () use ($helper, $source): int {
            $helper->trackFile($source, __FILE__);
            [, $nested] = $helper->capture(static fn () => $helper->trackDirectory($source, __DIR__, '*.php'));
            $helper->replay($source, $nested);

            return 42;
        });

        self::assertSame(42, $value);
        self::assertCount(2, $dependencies);

        $target = $this->createMock(DependencyTracker::class);
        $target->expects(self::once())->method('trackFileDependency')->with(__FILE__);
        $target->expects(self::once())->method('trackDirectoryDependency')->with(__DIR__, '*.php');
        $helper->replay($target, $dependencies);
    }

    public function testFailedCapturesDoNotLeakIntoLaterCaptures(): void
    {
        $helper = new DependencyHelper();
        $source = $this->createStub(DependencyTracker::class);

        try {
            $helper->capture(static function () use ($helper, $source): void {
                $helper->trackFile($source, __FILE__);

                throw new RuntimeException('failed');
            });
            self::fail('Expected the capture to throw.');
        } catch (RuntimeException $exception) {
            self::assertSame('failed', $exception->getMessage());
        }

        [, $dependencies] = $helper->capture(static fn () => 42);

        self::assertSame([], $dependencies);
    }
}
