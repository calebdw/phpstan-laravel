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

    #[Test]
    public function it_leaves_the_columns_alone_for_index_methods(): void
    {
        $parser       = self::getContainer()->getService('currentPhpVersionSimpleDirectParser');
        $schemaParser = new MigrationSchemaParser(
            $this->modelDatabaseHelper,
            $this->modelHelper,
            $this->createReflectionProvider(),
            self::getContainer()->getByType(InitializerExprTypeResolver::class),
        );

        $statements = $parser->parseString(<<<'PHP'
            <?php

            namespace Tests\Unit\SchemaParserIndexes;

            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            class CreatePostsTable
            {
                public function up(): void
                {
                    Schema::create('posts', function (Blueprint $table) {
                        $table->id();
                        $table->text('body');
                        $table->string('slug');

                        $table->index('slug');
                        $table->unique('slug');
                        $table->primary('id');
                        $table->fullText('body');
                        $table->rawIndex('(lower(slug))', 'posts_slug_lower');
                        $table->spatialIndex('slug');
                        $table->vectorIndex('body');
                    });

                    Schema::table('posts', function (Blueprint $table) {
                        $table->dropIndex('posts_slug_index');
                        $table->dropUnique('posts_slug_unique');
                        $table->dropPrimary('posts_id_primary');
                        $table->dropFullText('posts_body_fulltext');
                        $table->dropSpatialIndex('posts_slug_spatialindex');
                        $table->dropVectorIndex('posts_body_vectorindex');
                    });
                }
            }
            PHP);

        $schemaParser->addStatements($statements);

        $table = $this->modelDatabaseHelper->connections[$this->defaultConnection]->tables['posts'];

        self::assertSame(['id', 'body', 'slug'], array_keys($table->columns));
        self::assertSame('string', $table->columns['body']->readableType);
        self::assertSame('string', $table->columns['slug']->readableType);
    }

    #[Test]
    public function it_resolves_column_constant_initializers(): void
    {
        $parser       = self::getContainer()->getService('currentPhpVersionSimpleDirectParser');
        $schemaParser = new MigrationSchemaParser(
            $this->modelDatabaseHelper,
            $this->modelHelper,
            $this->createReflectionProvider(),
            self::getContainer()->getByType(InitializerExprTypeResolver::class),
        );

        $statements = $parser->parseString(<<<'PHP'
            <?php

            namespace Tests\Unit\SchemaParserColumns;

            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            use Tests\Unit\SchemaParserConstants\ColumnConstants;
            use Tests\Unit\SchemaParserConstants\ColumnEnum;

            class CreateContactsTable
            {
                public function up(): void
                {
                    Schema::create('contacts', function (Blueprint $table) {
                        $table->id();
                        $table->string(ColumnConstants::EMAIL)->nullable();
                        $table->string(ColumnConstants::CREATED_BY);
                        $table->string(ColumnConstants::NOT_A_STRING);
                        $table->string(ColumnEnum::Email);
                        $table->string('kept');
                    });

                    Schema::table('contacts', function (Blueprint $table) {
                        $table->dropColumn([ColumnConstants::CREATED_BY, 'kept']);
                    });
                }
            }
            PHP);

        $schemaParser->addStatements($statements);

        $table = $this->modelDatabaseHelper->connections[$this->defaultConnection]->tables['contacts'];

        // A constant that does not name a column, and an enum case whose
        // backing value happens to read like one, leave the table alone
        // rather than declaring or overwriting a column.
        self::assertSame(['id', 'email'], array_keys($table->columns));
        self::assertSame('string', $table->columns['email']->readableType);
        self::assertTrue($table->columns['email']->nullable);
    }

    #[Test]
    public function it_resolves_a_foreign_id_column_constant(): void
    {
        $parser       = self::getContainer()->getService('currentPhpVersionSimpleDirectParser');
        $schemaParser = new MigrationSchemaParser(
            $this->modelDatabaseHelper,
            $this->modelHelper,
            $this->createReflectionProvider(),
            self::getContainer()->getByType(InitializerExprTypeResolver::class),
        );

        $statements = $parser->parseString(<<<'PHP'
            <?php

            namespace Tests\Unit\SchemaParserForeignIds;

            use App\User;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            use Tests\Unit\SchemaParserConstants\ColumnConstants;

            class CreateNotesTable
            {
                public function up(): void
                {
                    Schema::create('notes', function (Blueprint $table) {
                        $table->id();
                        $table->foreignIdFor(User::class, ColumnConstants::CREATED_BY);
                        $table->foreignIdFor(User::class);
                    });
                }
            }
            PHP);

        $schemaParser->addStatements($statements);

        $table = $this->modelDatabaseHelper->connections[$this->defaultConnection]->tables['notes'];

        self::assertSame(['id', 'created_by', 'user_id'], array_keys($table->columns));
    }

    #[Test]
    public function it_drops_the_columns_behind_the_foreign_id_drops(): void
    {
        $parser       = self::getContainer()->getService('currentPhpVersionSimpleDirectParser');
        $schemaParser = new MigrationSchemaParser(
            $this->modelDatabaseHelper,
            $this->modelHelper,
            $this->createReflectionProvider(),
            self::getContainer()->getByType(InitializerExprTypeResolver::class),
        );

        $statements = $parser->parseString(<<<'PHP'
            <?php

            namespace Tests\Unit\SchemaParserForeignIdDrops;

            use App\Account;
            use App\User;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            use Tests\Unit\SchemaParserConstants\ColumnConstants;

            class CreateCommentsTable
            {
                public function up(): void
                {
                    Schema::create('comments', function (Blueprint $table) {
                        $table->id();
                        $table->string('body');
                        $table->foreignId('author_id');
                        $table->foreignIdFor(User::class);
                        $table->foreignIdFor(Account::class);
                        $table->foreignId(ColumnConstants::CREATED_BY);
                    });

                    Schema::table('comments', function (Blueprint $table) {
                        $table->dropConstrainedForeignId('author_id');
                        $table->dropForeignIdFor(User::class);
                        $table->dropConstrainedForeignIdFor(Account::class);
                        $table->dropConstrainedForeignId(ColumnConstants::CREATED_BY);
                    });
                }
            }
            PHP);

        $schemaParser->addStatements($statements);

        $table = $this->modelDatabaseHelper->connections[$this->defaultConnection]->tables['comments'];

        self::assertSame(['id', 'body'], array_keys($table->columns));
        self::assertSame('string', $table->columns['body']->readableType);
    }

    #[Test]
    public function it_survives_a_constant_that_is_not_there(): void
    {
        $parser       = self::getContainer()->getService('currentPhpVersionSimpleDirectParser');
        $schemaParser = new MigrationSchemaParser(
            $this->modelDatabaseHelper,
            $this->modelHelper,
            $this->createReflectionProvider(),
            self::getContainer()->getByType(InitializerExprTypeResolver::class),
        );

        // Reflection throws for a constant it cannot find, which a migration
        // left behind by a rename would otherwise turn into a failed run.
        $statements = $parser->parseString(<<<'PHP'
            <?php

            namespace Tests\Unit\SchemaParserMissing;

            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            use Tests\Unit\SchemaParserConstants\ColumnConstants;

            class CreateGhostsTable
            {
                public function up(): void
                {
                    Schema::create(ColumnConstants::NO_SUCH_TABLE, function (Blueprint $table) {
                        $table->id();
                    });

                    Schema::create('ghosts', function (Blueprint $table) {
                        $table->id();
                        $table->string(ColumnConstants::NO_SUCH_COLUMN);
                    });
                }
            }
            PHP);

        $schemaParser->addStatements($statements);

        $tables = $this->modelDatabaseHelper->connections[$this->defaultConnection]->tables;

        self::assertArrayNotHasKey('', $tables);
        self::assertSame(['id'], array_keys($tables['ghosts']->columns));
    }
}
