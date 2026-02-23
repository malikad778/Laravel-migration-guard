<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// ❌ DANGEROUS: Drops a table while old app instances may still reference it.
// laravel-migration-guard → check: drop_table | severity: BREAKING

return new class extends Migration {
    public function up(): void
    {
        Schema::drop('legacy_sessions');
    }

    public function down(): void
    {
        // Cannot restore dropped table data
    }
};
