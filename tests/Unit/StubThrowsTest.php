<?php

declare(strict_types=1);

namespace Tests\Unit;

use CalebDW\PhpstanLaravel\PhpDoc\StubFilesExtension;
use Illuminate\Database\Eloquent\Builder;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\FileTypeMapper;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

use function collect;
use function in_array;
use function is_a;

final class StubThrowsTest extends PHPStanTestCase
{
    /** Methods whose Laravel @throws tags are stale, so the stub drops them on purpose. */
    private const array EXEMPT = [
        // has() hands a MorphTo to hasMorph() rather than throwing a RuntimeException.
        Builder::class . '::has',
    ];

    #[Test]
    public function stubs_keep_the_exceptions_laravel_declares(): void
    {
        /** @var list<DynamicMethodThrowTypeExtension> $extensions */
        $extensions = self::getContainer()->getServicesByTag('phpstan.dynamicMethodThrowTypeExtension');
        $missing    = [];

        foreach ($this->stubbedMethods() as $method) {
            $name = $method->getDeclaringClass()->getName() . '::' . $method->getName();

            if (in_array($name, self::EXEMPT, true) || collect($extensions)->contains(static fn ($e) => $e->isMethodSupported($method))) {
                continue;
            }

            $declared = $method->getThrowType()?->getObjectClassNames() ?? [];

            foreach ($this->sourceThrows($method) as $exception) {
                if (collect($declared)->contains(static fn ($d) => is_a($exception, $d, true))) {
                    continue;
                }

                $missing[] = $name . '() ' . $exception;
            }
        }

        $this->assertSame([], $missing);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../phpstan-tests.neon'];
    }

    /** @return iterable<ExtendedMethodReflection> */
    private function stubbedMethods(): iterable
    {
        $container = self::getContainer();
        $provider  = $this->createReflectionProvider();
        $parser    = $container->getService('currentPhpVersionSimpleDirectParser');
        $finder    = new NodeFinder();

        foreach ($container->getByType(StubFilesExtension::class)->getFiles() as $file) {
            foreach ($finder->findInstanceOf($parser->parseFile($file), ClassLike::class) as $node) {
                $class = $node->namespacedName?->toString();

                if ($class === null || ! $provider->hasClass($class)) {
                    continue;
                }

                $reflection = $provider->getClass($class);

                foreach ($node->getMethods() as $method) {
                    $name = $method->name->toString();

                    if (! $reflection->hasNativeMethod($name)) {
                        continue;
                    }

                    yield $reflection->getNativeMethod($name);
                }
            }
        }
    }

    /** @return list<string> */
    private function sourceThrows(ExtendedMethodReflection $method): array
    {
        // PHPStan reflects the stub's PHPDoc, so Laravel's comes from native reflection.
        $native = new ReflectionMethod($method->getDeclaringClass()->getName(), $method->getName());
        $doc    = $native->getDocComment();

        if ($doc === false) {
            return [];
        }

        // A trait's PHPDoc resolves against the trait's imports, which PHPStan
        // reaches through the using class's file and the trait's name.
        $class = $native->getDeclaringClass();
        $trait = $method instanceof PhpMethodReflection ? $method->getDeclaringTrait()?->getName() : null;

        return self::getContainer()->getByType(FileTypeMapper::class)
            ->getResolvedPhpDoc($class->getFileName() ?: null, $class->getName(), $trait, $method->getName(), $doc)
            ->getThrowsTag()?->getType()->getObjectClassNames() ?? [];
    }
}
