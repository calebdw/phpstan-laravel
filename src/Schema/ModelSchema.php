<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use CalebDW\PhpstanLaravel\Support\ContainerHelper;
use Illuminate\Database\Eloquent\Model;

final class ModelSchema
{
    /** @var array<string, Connection> */
    public array $connections = [];

    private string $defaultConnection;

    private bool $initialized = false;

    public function __construct(
        private SchemaDumpParser $schemaDumpParser,
        private MigrationFileParser $migrationFileParser,
        private ContainerHelper $containerHelper,
        private SchemaCache|null $schemaCache = null,
    ) {
    }

    public function getModelConnectionName(Model $model): string
    {
        return $model->getConnectionName() ?? $this->getDefaultConnection();
    }

    public function getModelTable(Model $model): Table
    {
        return $this->connections[$this->getModelConnectionName($model)]
            ->tables[$model->getTable()];
    }

    public function hasConnection(string $connection): bool
    {
        $this->ensureInitialized();

        return isset($this->connections[$connection]);
    }

    public function hasModelTable(Model $model): bool
    {
        $connectionName = $this->getModelConnectionName($model);

        if (! $this->hasConnection($connectionName)) {
            return false;
        }

        return isset($this->connections[$connectionName]->tables[$model->getTable()]);
    }

    public function hasModelColumn(Model $model, string $column): bool
    {
        if (! $this->hasModelTable($model)) {
            return false;
        }

        return isset($this->getModelTable($model)->columns[$column]);
    }

    public function getOrCreateConnection(string|null $connectionName = null): Connection
    {
        $connectionName ??= $this->getDefaultConnection();

        // cannot use hasConnection here because it would trigger recursive loop
        if (isset($this->connections[$connectionName])) {
            return $this->connections[$connectionName];
        }

        $connection = new Connection($connectionName);

        $this->setConnection($connection);

        return $connection;
    }

    public function getModelColumn(Model $model, string $column): Column
    {
        return $this->getModelTable($model)->columns[$column];
    }

    public function setConnection(Connection $connection): void
    {
        $this->connections[$connection->name] = $connection;
    }

    public function dropConnection(string $connectionName): void
    {
        unset($this->connections[$connectionName]);
    }

    public function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        if ($this->schemaCache === null) {
            $this->parseSchema();

            return;
        }

        $schemaCache = $this->schemaCache;
        $inputHash   = $schemaCache->inputHash();
        $cached      = $schemaCache->load($inputHash);

        if ($cached !== null) {
            $this->connections = $cached;

            return;
        }

        $this->parseSchema();
        $schemaCache->save($inputHash, $this->connections);
    }

    private function parseSchema(): void
    {
        $this->schemaDumpParser->parseSchemaDumps($this);
        $this->migrationFileParser->parseMigrations($this);
    }

    public function getDefaultConnection(): string
    {
        if (! isset($this->defaultConnection)) {
            $this->defaultConnection = $this->containerHelper->resolve('config')['database.default'] ?? 'default';
        }

        return $this->defaultConnection;
    }
}
