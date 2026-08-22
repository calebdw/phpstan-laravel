<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Sql;

use RuntimeException;
use Throwable;

/** Thrown when a parser was unable to make sense of a schema dump. */
final class SqlParserFailure extends RuntimeException
{
    public static function create(string $message, Throwable|null $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
