<?php

namespace ModelMorphColumns;

use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

class MorphColumns extends Model
{
    protected $table = 'morph_columns';
}

function test(MorphColumns $model): void
{
    assertType('string', $model->numeric_type);
    assertType('int<0, max>', $model->numeric_id);
    assertType('string|null', $model->nullable_numeric_type);
    assertType('int<0, max>|null', $model->nullable_numeric_id);
    assertType('string', $model->uuid_type);
    assertType('string', $model->uuid_id);
    assertType('string|null', $model->nullable_uuid_type);
    assertType('string|null', $model->nullable_uuid_id);
    assertType('string', $model->ulid_type);
    assertType('string', $model->ulid_id);
    assertType('string|null', $model->nullable_ulid_type);
    assertType('string|null', $model->nullable_ulid_id);
}
