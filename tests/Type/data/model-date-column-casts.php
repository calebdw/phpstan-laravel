<?php

namespace ModelDateColumnCasts;

use DateTime;
use DateTimeImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use function PHPStan\Testing\assertType;

/** @implements CastsAttributes<array{raw: string|null}, string|null> */
class DateCast implements CastsAttributes
{
    /**
     * @param array<string, mixed> $attributes
     * @return array{raw: string|null}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return ['raw' => is_string($value) ? $value : null];
    }

    /**
     * @param string|null $value
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }
}

class DateColumns extends Model
{
    use SoftDeletes;

    protected $table = 'model_with_casts';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $casts = [
        'date' => 'date',
        'datetime' => 'datetime:Y-m-d H:i:s',
        'immutable_date' => 'immutable_date:Y-m-d',
        'immutable_datetime' => 'immutable_datetime',
        'created_at' => DateCast::class,
        'updated_at' => 'timestamp',
        'deleted_at' => 'immutable_datetime',
    ];
}

function test(DateColumns $model): void
{
    $model->date = new DateTimeImmutable();
    $model->datetime = '2026-11-16 12:00:00';
    $model->immutable_date = 1794830400;
    $model->immutable_datetime ??= new DateTime();
    $model->deleted_at ??= new DateTime();

    assertType('Carbon\Carbon', $model->date);
    assertType('Carbon\Carbon', $model->datetime);
    assertType('Carbon\CarbonImmutable', $model->immutable_date);
    assertType('Carbon\CarbonImmutable', $model->immutable_datetime);
    assertType('array{raw: string|null}|null', $model->created_at);
    assertType('int|null', $model->updated_at);
    assertType('Carbon\CarbonImmutable|null', $model->deleted_at);
}
