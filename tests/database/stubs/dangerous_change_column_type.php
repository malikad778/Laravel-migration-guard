<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ❌ DANGEROUS: Changes a column's type — causes full table rewrite and potential
// silent data truncation (e.g. VARCHAR(255) → VARCHAR(100) truncates existing values).
// laravel-migration-guard → check: change_column_type | severity: HIGH

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 100)->change(); // was VARCHAR(255)
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 255)->change();
        });
    }
};
