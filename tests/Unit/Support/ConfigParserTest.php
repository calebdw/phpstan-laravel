<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use CalebDW\PhpstanLaravel\Support\ConfigParser;
use CalebDW\PhpstanLaravel\Support\FileHelper;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\File\FileHelper as PHPStanFileHelper;
use PHPStan\Parser\Parser;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Attributes\Test;

class ConfigParserTest extends PHPStanTestCase
{
    /** @var Parser&object{count: int} */
    private Parser $parser;

    public function setUp(): void
    {
        $this->parser = new class (self::getContainer()->getService('currentPhpVersionSimpleDirectParser')) implements Parser {
            public int $count = 0;

            public function __construct(private Parser $parser)
            {
            }

            /** @return Stmt[] */
            public function parseFile(string $file): array
            {
                $this->count++;

                return $this->parser->parseFile($file);
            }

            /** @return Stmt[] */
            public function parseString(string $sourceCode): array
            {
                return $this->parser->parseString($sourceCode);
            }
        };
    }

    #[Test]
    public function it_parses_each_file_at_most_once(): void
    {
        $parser = $this->getConfigParser();
        $scope  = $this->getScope();

        self::assertSame('string', $parser->getType('package.string', $scope)?->describe(VerbosityLevel::precise()));
        self::assertSame(1, $this->parser->count);

        self::assertSame('int', $parser->getType('package.int', $scope)?->describe(VerbosityLevel::precise()));
        self::assertSame('string', $parser->getType('package.nested.deep.key', $scope)?->describe(VerbosityLevel::precise()));
        self::assertSame('string', $parser->getType('package.string', $scope)?->describe(VerbosityLevel::precise()));
        self::assertSame(1, $this->parser->count);

        self::assertSame("'redis'|'sync'", $parser->getType('documented.driver', $scope)?->describe(VerbosityLevel::precise()));
        self::assertSame(2, $this->parser->count);
    }

    #[Test]
    public function it_does_not_parse_anything_for_unknown_keys(): void
    {
        $parser = $this->getConfigParser();
        $scope  = $this->getScope();

        self::assertNull($parser->getType('missing.key', $scope));
        self::assertNull($parser->getType('missing.key', $scope));
        self::assertSame(0, $this->parser->count);

        self::assertNull($parser->getType('package.missing', $scope));
        self::assertNull($parser->getType('package.missing', $scope));
        self::assertSame(1, $this->parser->count);
    }

    #[Test]
    public function it_lists_the_files_a_key_can_come_from(): void
    {
        self::assertSame(['app.php'], ConfigParser::candidateFiles('app'));
        self::assertSame(['app.php', 'app/name.php'], ConfigParser::candidateFiles('app.name'));
        self::assertSame(
            ['email.php', 'email/engineering.php', 'email/engineering/designs.php'],
            ConfigParser::candidateFiles('email.engineering.designs'),
        );
    }

    #[Test]
    public function it_keys_a_nested_file_by_its_path(): void
    {
        $parser = $this->getConfigParser();
        $scope  = $this->getScope();

        self::assertSame('string', $parser->getType('module.queue.connection', $scope)?->describe(VerbosityLevel::precise()));

        // `queue` is the file's name, not its key
        self::assertNull($parser->getType('queue.connection', $scope));
    }

    #[Test]
    public function it_keeps_files_that_share_a_name_apart(): void
    {
        $parser = $this->getConfigParser();
        $scope  = $this->getScope();

        self::assertSame('string', $parser->getType('package.string', $scope)?->describe(VerbosityLevel::precise()));
        self::assertSame('int', $parser->getType('module.package.from', $scope)?->describe(VerbosityLevel::precise()));
    }

    #[Test]
    public function it_prefers_the_nested_file_over_the_key_it_shadows(): void
    {
        $parser = $this->getConfigParser();
        $scope  = $this->getScope();

        // config/override.php declares nested.from as an int, and Laravel
        // loads config/override/nested.php over the top of it
        self::assertSame('string', $parser->getType('override.nested.from', $scope)?->describe(VerbosityLevel::precise()));
        self::assertSame('string', $parser->getType('override.own', $scope)?->describe(VerbosityLevel::precise()));
    }

    #[Test]
    public function it_does_not_touch_the_filesystem_without_directories(): void
    {
        $parser = $this->getConfigParser([]);

        self::assertFalse($parser->hasDirectories());
        self::assertNull($parser->getType('package.string', $this->getScope()));
        self::assertSame(0, $this->parser->count);
    }

    /** @param  list<non-empty-string>|null $directories */
    private function getConfigParser(array|null $directories = null): ConfigParser
    {
        return new ConfigParser(
            $directories ?? [__DIR__ . '/../../Type/data/config'],
            new FileHelper(self::getContainer()->getByType(PHPStanFileHelper::class)),
            $this->parser,
            self::getContainer()->getByType(FileTypeMapper::class),
            true,
        );
    }

    private function getScope(): Scope
    {
        return self::createScopeFactory(
            self::createReflectionProvider(),
            self::getContainer()->getByType(TypeSpecifier::class),
        )->create(ScopeContext::create(__FILE__));
    }
}
