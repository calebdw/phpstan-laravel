<?php

declare(strict_types=1);

namespace Tests\Rules\Queue\Data;

use Illuminate\Support\Facades\DB;

DB::transaction(static function (): void {
    NotifyOwner::dispatch(1)->onConnection('after_commit_test');
});
