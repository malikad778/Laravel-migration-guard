<?php

use Malikad778\MigrationGuard\Issues\IssueSeverity;

// ============================================================
// Spec §9.1 — Unit Tests: DropColumnCheck
// ============================================================

it('detects dropColumn as BREAKING', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    PHP);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('drop_column')
        ->and($issues[0]->severity)->toBe(IssueSeverity::BREAKING)
        ->and($issues[0]->table)->toBe('invoices')
        ->and($issues[0]->column)->toBe('amount');
});

it('detects dropColumns (array form) as BREAKING', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumns(['status', 'note']);
        });
    PHP);

    expect($issues)->toHaveCount(2)
        ->and($issues[0]->checkId)->toBe('drop_column')
        ->and($issues[0]->column)->toBe('status')
        ->and($issues[1]->column)->toBe('note');
});

// ============================================================
// Spec §9.2 — False Positive: dropColumn inside Schema::create should NOT fire
// ============================================================

it('does NOT detect dropColumn inside Schema::create', function () {
    $issues = analyse(<<<'PHP'
        Schema::create('invoices', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    PHP);

    expect($issues)->toHaveCount(0);
});
