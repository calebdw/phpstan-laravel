<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrow_records', static fn (Blueprint $table) => $table->id());
        Schema::table('arrow_records', static fn (Blueprint $table) => $table->string('label')->nullable());
        Schema::table('arrow_records', static fn (Blueprint $table) => $table->after('id', static fn ($t) => $t->integer('age')));
    }
};
