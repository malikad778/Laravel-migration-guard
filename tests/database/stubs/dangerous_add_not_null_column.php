<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ❌ DANGEROUS: Adds a NOT NULL column without a default value.
// On MySQL < 8.0 this requires a full table rewrite, locking reads & writes.
// laravel-migration-guard → check: add_column_not_null | severity: HIGH

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
