<?php

declare(strict_types=1);

namespace Tests\Unit\DumpWithRanMigrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBodyToPostsTable extends Migration
{
    public function up(): void
    {
        Schema::table('dump_posts', static function (Blueprint $table) {
            $table->text('body');
        });
    }
}
