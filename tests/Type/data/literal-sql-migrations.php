<?php

namespace LiteralSqlMigrations;

use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

class LiteralRecord extends Model
{
    protected $table = 'literal_records';
}

class OtherRecord extends LiteralRecord
{
    protected $connection = 'foo';
}

function properties(LiteralRecord $record, OtherRecord $other): void
{
    assertType('int', $record->id);
    assertType('string|null', $record->label);
    assertType('float', $record->amount);
    assertType('bool', $other->active);
}
