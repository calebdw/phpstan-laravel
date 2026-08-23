<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Sql;

use RuntimeException;
use Throwable;

use function sprintf;

/** Thrown when a parser was unable to make sense of a schema dump. */
final class SqlParserFailure extends RuntimeException
{
    public static function create(string $message, Throwable|null $previous = null): self
    {
        return new self($message, previous: $previous);
    }

    public static function unreadableDump(string $path, Throwable $previous): self
    {
        $message = <<<'MESSAGE'
            Unable to parse the schema dump at %s. %s
            The tables it defines would be missing from model properties with nothing to
            say so, which surfaces later as confusing errors about properties that exist.
            Fix the dump, choose another parser with laravel.sqlParser, or set
            laravel.scanSchema to false to skip schema dumps entirely.
            MESSAGE;

        return new self(
            sprintf($message, $path, $previous->getMessage()),
            previous: $previous,
        );
    }
}
