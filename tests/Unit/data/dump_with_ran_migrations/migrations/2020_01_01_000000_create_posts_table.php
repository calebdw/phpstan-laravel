<?php

declare(strict_types=1);

namespace Tests\Unit\DumpWithRanMigrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('dump_posts', static function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
    }
}
