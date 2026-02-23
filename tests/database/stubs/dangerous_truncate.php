<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ❌ DANGEROUS: Truncates a table inside a migration — data is permanently destroyed.
// Migrations are the wrong place for data deletion in production.
// laravel-migration-guard → check: truncate | severity: BREAKING

return new class extends Migration {
    public function up(): void
    {
        DB::table('cache')->truncate();
    }

    public function down(): void
    {
        // Cannot restore truncated data
    }
};
