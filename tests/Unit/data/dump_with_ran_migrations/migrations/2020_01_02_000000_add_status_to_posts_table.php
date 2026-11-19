<?php

declare(strict_types=1);

namespace Tests\Unit\DumpWithRanMigrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddStatusToPostsTable extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dump_posts ADD status ENUM('draft', 'published') NOT NULL");
    }
}
