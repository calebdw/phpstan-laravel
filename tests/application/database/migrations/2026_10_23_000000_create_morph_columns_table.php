<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('morph_columns', static function (Blueprint $table): void {
            $table->numericMorphs('numeric');
            $table->nullableNumericMorphs('nullable_numeric');
            $table->uuidMorphs('uuid');
            $table->nullableUuidMorphs('nullable_uuid');
            $table->ulidMorphs('ulid');
            $table->nullableUlidMorphs('nullable_ulid');
        });
    }
};
