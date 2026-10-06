<?php

namespace Tests\Unit\SchemaParserConstants;

class ColumnConstants
{
    public const EMAIL = 'email';

    private const PREFIX = 'created';

    /** @var string */
    public const CREATED_BY = self::PREFIX . '_by';

    public const NOT_A_STRING = 1;
}

enum ColumnEnum: string
{
    case Email = 'email';
}
