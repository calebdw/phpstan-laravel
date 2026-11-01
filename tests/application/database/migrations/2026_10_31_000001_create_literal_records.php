<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE literal_records (id BIGINT NOT NULL, label VARCHAR(80), amount DECIMAL(10, 2) NOT NULL);');
        DB::connection('foo')->unprepared('CREATE TABLE literal_records (active BOOLEAN NOT NULL);');
    }
};
