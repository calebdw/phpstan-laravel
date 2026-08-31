<?php

declare(strict_types=1);

namespace Tests\Unit;

use CalebDW\PhpstanLaravel\StubFilesExtension;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use PHPStan\File\FileHelper as PHPStanFileHelper;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function basename;
use function bin2hex;
use function file_put_contents;
use function glob;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sort;
use function sys_get_temp_dir;
use function unlink;

use const GLOB_ONLYDIR;

class StubFilesExtensionTest extends PHPStanTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpstan-laravel-stubs-' . bin2hex(random_bytes(8));

        mkdir($this->directory . '/common/Nested', recursive: true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*/*/*.stub') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($this->directory . '/*/*.stub') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($this->directory . '/*/*', GLOB_ONLYDIR) ?: [] as $directory) {
            rmdir($directory);
        }

        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            rmdir($directory);
        }

        rmdir($this->directory);
    }

    #[Test]
    public function versioned_stubs_override_common_and_older_versions(): void
    {
        $older   = 12;
        $current = 13;
        $newer   = 14;

        mkdir($this->directory . '/' . $older);
        mkdir($this->directory . '/' . $current);
        mkdir($this->directory . '/' . $newer);

        file_put_contents($this->directory . '/common/Shared.stub', 'common');
        file_put_contents($this->directory . '/common/Nested/Common.stub', 'common');
        file_put_contents($this->directory . '/' . $older . '/Shared.stub', 'older');
        file_put_contents($this->directory . '/' . $older . '/Older.stub', 'older');
        file_put_contents($this->directory . '/' . $current . '/Shared.stub', 'current');
        file_put_contents($this->directory . '/' . $current . '/Current.stub', 'current');
        file_put_contents($this->directory . '/' . $newer . '/Newer.stub', 'newer');

        $extension = new StubFilesExtension(
            new FileHelper(self::getContainer()->getByType(PHPStanFileHelper::class)),
            $this->directory,
            $current . '.0',
        );

        $files = $extension->getFiles();
        $names = array_map(basename(...), $files);
        sort($names);

        $this->assertSame(['Common.stub', 'Current.stub', 'Older.stub', 'Shared.stub'], $names);
        $this->assertContains($this->directory . '/' . $current . '/Shared.stub', $files);
    }
}
