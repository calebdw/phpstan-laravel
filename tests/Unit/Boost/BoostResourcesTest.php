<?php

declare(strict_types=1);

namespace Tests\Unit\Boost;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

use function sys_get_temp_dir;
use function token_get_all;

use const TOKEN_PARSE;

class BoostResourcesTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function boostResourceProvider(): iterable
    {
        $finder = Finder::create()
            ->files()
            ->name('*.blade.php')
            ->in(__DIR__ . '/../../../resources/boost');

        foreach ($finder as $file) {
            yield $file->getRelativePathname() => [$file->getPathname()];
        }
    }

    #[Test]
    #[DataProvider('boostResourceProvider')]
    public function it_compiles_to_valid_php(string $path): void
    {
        $filesystem = new Filesystem();
        $compiler   = new BladeCompiler($filesystem, sys_get_temp_dir());

        self::assertIsArray(token_get_all($compiler->compileString($filesystem->get($path)), TOKEN_PARSE));
    }
}
