<?php

declare(strict_types=1);

namespace Database\Migrations;

use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForeignReferencesTable extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_references', static function (Blueprint $table) {
            $table->foreignUuidFor(User::class);
            $table->foreignUlidFor(User::class, 'owner_ulid')->nullable();
            $table->foreignUuidFor(User::class, 'reviewer_uuid');
        });
    }
}
