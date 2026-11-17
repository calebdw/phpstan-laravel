<?php

namespace ModelForeignReferenceColumns;

use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

class ForeignReference extends Model
{
    protected $table = 'foreign_references';
}

function test(ForeignReference $model): void
{
    assertType('string', $model->user_id);
    assertType('string|null', $model->owner_ulid);
    assertType('string', $model->reviewer_uuid);
}
