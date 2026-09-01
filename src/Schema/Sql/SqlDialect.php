<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema\Sql;

enum SqlDialect
{
    case MySql;
    case Postgres;
}
