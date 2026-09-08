<?php

declare(strict_types=1);

namespace Tests\Unit;

use CalebDW\PhpstanLaravel\Schema\MigrationSchemaParser;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\Concerns\HasDatabaseHelper;

use function array_keys;
use function sprintf;

class MigrationSchemaParserTest extends PHPStanTestCase
{
    use HasDatabaseHelper;

    /** @return iterable<string, array{string}> */
    public static function tableConstants(): iterable
    {
        yield 'untyped' => ['Constants::USERS'];
        yield 'PHPDoc type' => ['Constants::DOCUMENTED_USERS'];
        yield 'inherited initializer' => ['InheritedConstants::DOCUMENTED_USERS'];
        yield 'native type' => ['TypedConstants::USERS'];
        yield 'typed expression' => ['TypedConstants::CONCATENATED_USERS'];
    }

    #[Test]
    #[DataProvider('tableConstants')]
    public function it_resolves_table_constant_initializers(string $constant): void
    {
        $parser       = self::getContainer()->getService('currentPhpVersionSimpleDirectParser');
        $schemaParser = new MigrationSchemaParser(
            $this->modelDatabaseHelper,
            $this->modelHelper,
            $this->createReflectionProvider(),
            self::getContainer()->getByType(InitializerExprTypeResolver::class),
        );

        $statements = $parser->parseString(sprintf(<<<'PHP'
            <?php

            namespace Tests\Unit\SchemaParserConstants;

            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            class CreateUsersTable
            {
                public function up(): void
                {
                    Schema::create(%1$s, function (Blueprint $table) {
                        $table->id();
                    });

                    Schema::table(%1$s, function (Blueprint $table) {
                        $table->string('email')->nullable();
                    });
                }
            }
            PHP, $constant));

        $schemaParser->addStatements($statements);

        $tables = $this->modelDatabaseHelper->connections[$this->defaultConnection]->tables;

        self::assertArrayHasKey('users', $tables);
        self::assertSame(['id', 'email'], array_keys($tables['users']->columns));
        self::assertSame('string', $tables['users']->columns['email']->readableType);
        self::assertTrue($tables['users']->columns['email']->nullable);
    }
}
