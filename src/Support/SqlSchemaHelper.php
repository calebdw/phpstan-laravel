<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use CalebDW\PhpstanLaravel\Schema\Column;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlDialect;
use CalebDW\PhpstanLaravel\Schema\Sql\SqlParserManager;
use CalebDW\PhpstanLaravel\Schema\Table;
use CalebDW\PhpstanLaravel\Schema\Type\PostgresDataTypeToPhpTypeConverter;
use CalebDW\PhpstanLaravel\Schema\Type\SqlDataTypeToPhpTypeConverter;

final class SqlSchemaHelper
{
    public function __construct(
        private SqlParserManager $parserManager,
        private SqlDataTypeToPhpTypeConverter $converter,
        private PostgresDataTypeToPhpTypeConverter $postgresConverter,
        private ContainerHelper $containerHelper,
    ) {
    }

    /** @return list<Table> */
    public function tables(string $sql, string $connectionName): array
    {
        $driver = null;

        if ($this->parserManager->getDefaultDriver() === SqlParserManager::DRIVER_AUTO) {
            $config = $this->containerHelper->resolve('config');

            if (($config['database.connections.' . $connectionName . '.driver'] ?? null) === 'pgsql') {
                $driver = SqlParserManager::DRIVER_POSTGRES;
            }
        }

        $tables = [];

        foreach ($this->parserManager->driver($driver)->parseTables($sql) as $definition) {
            $table = new Table($definition->name);

            foreach ($definition->columns as $column) {
                $converter = match ($column->dialect) {
                    SqlDialect::MySql => $this->converter,
                    SqlDialect::Postgres => $this->postgresConverter,
                };

                $table->setColumn(new Column(
                    $column->name,
                    $converter->convert($column->type, $column->typeOptions, $column->values),
                    $column->nullable,
                ));
            }

            $tables[] = $table;
        }

        return $tables;
    }
}
