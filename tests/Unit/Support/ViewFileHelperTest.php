<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use CalebDW\PhpstanLaravel\Support\ViewFileHelper;
use Illuminate\Container\Container;
use PHPStan\File\FileHelper as PHPStanFileHelper;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;

use function iterator_to_array;

class ViewFileHelperTest extends PHPStanTestCase
{
    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../../extension.neon'];
    }

    #[Test]
    public function it_survives_an_application_that_was_never_booted(): void
    {
        // The process that reads the result cache builds the services without
        // ever loading the bootstrap file, so nothing may reach for the view
        // finder before it is asked a question.
        $this->withoutApplication(function (): void {
            $helper = $this->helper();

            self::assertSame([], $helper->getViewDirectories());
            self::assertSame([], iterator_to_array($helper->getRootViewFilePaths()));
            self::assertSame([], iterator_to_array($helper->getAllViewFilePaths()));
            self::assertSame([], iterator_to_array($helper->getAllViewNames()));
        });
    }

    #[Test]
    public function it_uses_the_configured_directories_without_asking_the_container(): void
    {
        $directory = __DIR__ . '/../../Type/data';

        $this->withoutApplication(function () use ($directory): void {
            self::assertSame([$directory], $this->helper([$directory])->getViewDirectories());
        });
    }

    #[Test]
    public function it_resolves_the_directories_from_the_finder(): void
    {
        self::assertNotSame([], $this->helper()->getViewDirectories());
    }

    /** @param list<non-empty-string> $directories */
    private function helper(array $directories = []): ViewFileHelper
    {
        return new ViewFileHelper(
            $directories,
            new FileHelper(self::getContainer()->getByType(PHPStanFileHelper::class)),
            new ContainerHelper(self::createReflectionProvider()),
        );
    }

    private function withoutApplication(callable $callback): void
    {
        $application = Container::getInstance();

        Container::setInstance(new Container());

        try {
            $callback();
        } finally {
            Container::setInstance($application);
        }
    }
}
