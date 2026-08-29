<?php

declare(strict_types=1);

namespace Tests\Unit\Internal;

use CalebDW\PhpstanLaravel\Internal\FileHelper;
use PHPStan\File\FileHelper as PHPStanFileHelper;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;

use function array_keys;
use function bin2hex;
use function file_put_contents;
use function is_dir;
use function is_link;
use function iterator_to_array;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sort;
use function sprintf;
use function symlink;
use function sys_get_temp_dir;
use function unlink;

class FileHelperTest extends PHPStanTestCase
{
    private string $directory;
    private FileHelper $fileHelper;

    public function setUp(): void
    {
        $this->directory  = sys_get_temp_dir() . '/phpstan-laravel-file-helper-' . bin2hex(random_bytes(8));
        $this->fileHelper = new FileHelper(
            self::getContainer()->getByType(PHPStanFileHelper::class),
        );

        mkdir($this->directory . '/sub/deeper', recursive: true);
        mkdir($this->directory . '/empty');

        // more entries than a single directory read buffer holds on the
        // filesystems this scan works around, so a dropped first chunk would
        // show up in the result count
        for ($i = 0; $i < 40; $i++) {
            file_put_contents(sprintf('%s/2024_01_01_%06d_create_table.php', $this->directory, $i), '<?php');
        }

        file_put_contents($this->directory . '/UPPERCASE.PHP', '<?php');
        file_put_contents($this->directory . '/notes.txt', 'not a match');
        file_put_contents($this->directory . '/sub/nested.php', '<?php');
        file_put_contents($this->directory . '/sub/deeper/deep.php', '<?php');
        file_put_contents($this->directory . '/sub/schema.sql', 'CREATE TABLE t (id INT);');

        symlink($this->directory . '/sub', $this->directory . '/link');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    #[Test]
    public function it_finds_matching_files_recursively(): void
    {
        $files = $this->fileHelper->getFiles([$this->directory], '/\.php$/i');

        self::assertCount(43, $files);
        self::assertArrayHasKey($this->directory . '/UPPERCASE.PHP', $files);
        self::assertArrayHasKey($this->directory . '/sub/nested.php', $files);
        self::assertArrayHasKey($this->directory . '/sub/deeper/deep.php', $files);
        self::assertContainsOnlyInstancesOf(SplFileInfo::class, $files);
    }

    #[Test]
    public function it_only_scans_the_given_directory_when_not_recursive(): void
    {
        $files = $this->fileHelper->getFiles([$this->directory], '/\.php$/i', recursive: false);

        self::assertCount(41, $files);
        self::assertArrayNotHasKey($this->directory . '/sub/nested.php', $files);
    }

    #[Test]
    public function it_matches_the_filter_against_the_full_pathname(): void
    {
        $files = $this->fileHelper->getFiles([$this->directory], '/\.dump|\.sql/i');

        self::assertSame([$this->directory . '/sub/schema.sql'], array_keys($files));
    }

    #[Test]
    public function it_returns_every_file_without_a_filter(): void
    {
        $files = $this->fileHelper->getFiles([$this->directory]);

        self::assertCount(45, $files);
        self::assertArrayHasKey($this->directory . '/notes.txt', $files);
        self::assertArrayNotHasKey($this->directory . '/empty', $files);
    }

    #[Test]
    public function it_does_not_follow_symlinked_directories(): void
    {
        $files = $this->fileHelper->getFiles([$this->directory], '/\.php$/i');

        self::assertArrayNotHasKey($this->directory . '/link/nested.php', $files);
    }

    #[Test]
    public function it_resolves_glob_patterns(): void
    {
        $files = $this->fileHelper->getFiles([$this->directory . '/su*'], '/\.php$/i');

        self::assertSame(
            [$this->directory . '/sub/nested.php', $this->directory . '/sub/deeper/deep.php'],
            array_keys($files),
        );
    }

    #[Test]
    public function it_ignores_missing_directories(): void
    {
        self::assertSame([], $this->fileHelper->getFiles([$this->directory . '/nope'], '/\.php$/i'));
    }

    #[Test]
    public function it_finds_the_same_files_as_the_spl_iterators_it_replaces(): void
    {
        $files = $this->fileHelper->getFiles([$this->directory], '/\.php$/i');

        /** @var array<string, SplFileInfo> $splFiles */
        $splFiles = iterator_to_array(
            new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory)),
                '/\.php$/i',
            ),
        );

        $paths = array_keys($files);
        sort($paths);

        $splPaths = array_keys($splFiles);
        sort($splPaths);

        self::assertSame($splPaths, $paths);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path) && ! is_link($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
