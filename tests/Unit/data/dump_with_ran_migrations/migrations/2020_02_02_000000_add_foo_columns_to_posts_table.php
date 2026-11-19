<?php

declare(strict_types=1);

namespace Tests\Unit\DumpWithRanMigrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFooColumnsToPostsTable extends Migration
{
    public function up(): void
    {
        Schema::connection('foo')->table('dump_posts', static function (Blueprint $table) {
            $table->text('body');
        });

        DB::connection('foo')->statement('CREATE TABLE obsolete_posts (id INT NOT NULL)');
    }
}
