<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ResultCache;

use CalebDW\PhpstanLaravel\Schema\SchemaCache;
use PHPStan\Analyser\ResultCache\ResultCacheMetaExtension;

final class SchemaResultCacheMetaExtension implements ResultCacheMetaExtension
{
    public function __construct(private SchemaCache $schemaCache)
    {
    }

    public function getKey(): string
    {
        return 'phpstan-laravel.schema-inputs';
    }

    public function getHash(): string
    {
        return $this->schemaCache->inputHash();
    }
}
