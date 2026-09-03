<?php

declare(strict_types=1);

namespace Tests\Unit;

use CalebDW\PhpstanLaravel\ResultCache\SchemaResultCacheMetaExtension;
use CalebDW\PhpstanLaravel\Schema\ModelSchema;
use CalebDW\PhpstanLaravel\Schema\SchemaCache;
use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\Concerns\HasDatabaseHelper;

use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function mkdir;
use function str_replace;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

class SchemaCacheTest extends PHPStanTestCase
{
    use HasDatabaseHelper;

    private string $directory;

    public function setUp(): void
    {
        $this->setUpHasDatabaseHelper();

        $this->directory = sys_get_temp_dir() . '/phpstan-laravel-schema-' . uniqid();
        mkdir($this->directory);
    }

    #[Test]
    public function data_only_input_changes_change_the_input_hash(): void
    {
        $migration = $this->directory . '/2020_01_30_000000_create_users_table.php';
        $source    = file_get_contents(__DIR__ . '/data/basic_migration/2020_01_30_000000_create_users_table.php');
        self::assertIsString($source);
        file_put_contents($migration, $source);

        $cache = $this->cache($this->directory, $this->directory . '/cache');
        $hash  = $cache->inputHash();

        file_put_contents($migration, str_replace('public function up(): void', "public function up(): void\n    {\n        // Data-only change.\n    }\n\n    public function schema(): void", $source));
        touch($migration, filemtime($migration) + 1);

        self::assertNotSame($hash, $this->cache($this->directory, $this->directory . '/cache')->inputHash());
    }

    #[Test]
    public function schema_changes_change_the_input_hash(): void
    {
        $migration = $this->directory . '/2020_01_30_000000_create_users_table.php';
        $source    = file_get_contents(__DIR__ . '/data/basic_migration/2020_01_30_000000_create_users_table.php');
        self::assertIsString($source);
        file_put_contents($migration, $source);

        $hash = $this->cache($this->directory, $this->directory . '/cache')->inputHash();

        file_put_contents($migration, str_replace("\$table->string('email')->unique();", "\$table->integer('email');", $source));
        touch($migration, filemtime($migration) + 1);

        self::assertNotSame($hash, $this->cache($this->directory, $this->directory . '/cache')->inputHash());
    }

    #[Test]
    public function meta_extension_uses_the_schema_input_hash(): void
    {
        $migration = $this->directory . '/2020_01_30_000000_create_users_table.php';
        $source    = file_get_contents(__DIR__ . '/data/basic_migration/2020_01_30_000000_create_users_table.php');
        self::assertIsString($source);
        file_put_contents($migration, $source);

        $cache     = $this->cache($this->directory, $this->directory . '/cache');
        $extension = new SchemaResultCacheMetaExtension($cache);

        self::assertSame('phpstan-laravel.schema-inputs', $extension->getKey());
        self::assertSame($cache->inputHash(), $extension->getHash());
    }

    #[Test]
    public function migration_file_discovery_is_reused_during_the_run(): void
    {
        $parser = $this->getMigrationHelper([$this->directory]);

        self::assertSame([], $parser->files());

        file_put_contents($this->directory . '/migration.php', '<?php');

        self::assertSame([], $parser->files());
    }

    private function schema(string $migrationDirectory, string $cacheDirectory): ModelSchema
    {
        $migrationParser = $this->getMigrationHelper([$migrationDirectory]);
        $dumpParser      = $this->getSquashedMigrationHelper([], scan: false);
        $cache           = new SchemaCache($migrationParser, $dumpParser, $cacheDirectory);

        return new ModelSchema($dumpParser, $migrationParser, self::getContainer()->getByType(ContainerHelper::class), $cache);
    }

    private function cache(string $migrationDirectory, string $cacheDirectory): SchemaCache
    {
        return new SchemaCache(
            $this->getMigrationHelper([$migrationDirectory]),
            $this->getSquashedMigrationHelper([], scan: false),
            $cacheDirectory,
        );
    }
}
