<?php

namespace ModelDateColumnAssignments;

use Carbon\Carbon;
use DateTimeImmutable;
use ModelDateColumnCasts\DateColumns;

function test(DateColumns $model): void
{
    $model->created_at = new DateTimeImmutable();
    $model->created_at = Carbon::now();
    $model->created_at = '2026-10-23';
    $model->created_at = 1;
    $model->created_at = 1.5;
    $model->created_at = null;
    $model->updated_at = new DateTimeImmutable();
    $model->deleted_at = Carbon::now();
    $model->created_at = ['raw' => '2026-10-23'];
}
