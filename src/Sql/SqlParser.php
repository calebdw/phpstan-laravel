<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Sql;

interface SqlParser
{
    /**
     * Describe every table created by the given schema dump.
     *
     * Implementations must return at most one definition per table name. Where
     * a dump creates the same table more than once - `DROP TABLE IF EXISTS`
     * followed by `CREATE TABLE`, repeated - the last definition wins, since
     * that is the table you would be left with after replaying the dump.
     *
     * @return list<TableDefinition>
     *
     * @throws SqlParserFailure
     */
    public function parseTables(string $sql): array;
}
