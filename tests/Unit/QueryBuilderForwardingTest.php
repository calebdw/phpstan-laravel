<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\FirstCustomQueryBuilder;
use App\SecondCustomQueryBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryBuilderForwardingTest extends TestCase
{
    #[Test]
    public function itPreservesQueryMacroResultsOnlyOnDirectCalls(): void
    {
        $connection = new Connection(null);
        $first      = new FirstCustomQueryBuilder($connection);
        $second     = new SecondCustomQueryBuilder($connection);

        self::assertSame(1, $first->customScalar(1));
        self::assertSame('1', $second->customScalar(1));
        self::assertSame(11, $first->count());

        foreach ([$first, $second] as $query) {
            $builder = new Builder($query);

            self::assertSame($builder, $builder->customScalar(1));
            self::assertSame($query->count(), $builder->count());
        }
    }
}
