<?php

declare(strict_types=1);

namespace Tests\Rules\Queue\Data;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

use function dispatch;

class NotifyOwnerFix implements ShouldQueue
{
    use Dispatchable;

    public function __construct(public int $productId)
    {
    }
}

DB::transaction(static function (): void {
    NotifyOwnerFix::dispatch(1);
    dispatch(new NotifyOwnerFix(1));
    NotifyOwnerFix::dispatch(1)->onQueue('default');
});
