<?php

namespace ArrowMigrations;

use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

class ArrowRecord extends Model
{
    protected $table = 'arrow_records';
}

function properties(ArrowRecord $record): void
{
    assertType('int<0, max>', $record->id);
    assertType('string|null', $record->label);
    assertType('int', $record->age);
}
