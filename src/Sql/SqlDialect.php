<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Sql;

enum SqlDialect
{
    case MySql;
    case Postgres;
}
