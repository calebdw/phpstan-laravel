<?php

declare(strict_types=1);

namespace Tests\Unit\UnsignedIntegerColumns;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCountersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('counters', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id');
            $table->unsignedBigInteger('views');
            $table->unsignedInteger('clicks');
            $table->unsignedMediumInteger('shares');
            $table->unsignedSmallInteger('replies');
            $table->unsignedTinyInteger('flags');
            $table->integer('score')->unsigned();
            $table->bigInteger('offset')->nullable()->unsigned();
            $table->integer('balance');
            $table->bigInteger('delta');
            $table->year('opened_year');
            $table->morphs('subject');
        });
    }
}
