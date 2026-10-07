<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Http\Requests\StripRequest;
use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use CalebDW\PhpstanLaravel\Support\ValidationHelper;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ValidationHelper::class)]
class ValidationHelperTest extends PHPStanTestCase
{
    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../../extension.neon'];
    }

    /**
     * `StripRequest` requires `info` and gives it a bare `array` rule with an
     * optional child, so whether the key is always there is decided by the
     * stripping the application asked for.
     */
    #[Test]
    public function it_keeps_a_stripped_parent_when_the_application_includes_unvalidated_keys(): void
    {
        self::assertSame('array{info?: array{status?: string}}', $this->shape());

        $factory = Container::getInstance()->make(ValidationFactory::class);
        $factory->includeUnvalidatedArrayKeys();

        try {
            self::assertSame('array{info: array{status?: string}}', $this->shape());
        } finally {
            $factory->excludeUnvalidatedArrayKeys();
        }

        self::assertSame('array{info?: array{status?: string}}', $this->shape());
    }

    /**
     * A fresh helper every time, because each one answers the container once
     * and remembers it.
     */
    private function shape(): string|null
    {
        $helper = new ValidationHelper(
            self::getContainer()->getService('currentPhpVersionSimpleDirectParser'),
            self::createReflectionProvider(),
            self::getContainer()->getByType(TypeHelper::class),
            self::getContainer()->getByType(CallHelper::class),
            self::getContainer()->getByType(ContainerHelper::class),
        );

        return $helper
            ->validatedShape(self::createReflectionProvider()->getClass(StripRequest::class))
            ?->describe(VerbosityLevel::precise());
    }
}
