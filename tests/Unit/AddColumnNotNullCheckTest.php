<?php

use Malikad778\MigrationGuard\Issues\IssueSeverity;

// ============================================================
// Spec §9.1 — Unit Tests: AddColumnNotNullCheck
// ============================================================

it('detects NOT NULL column without default as HIGH', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->string('status');
        });
    PHP);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('add_column_not_null')
        ->and($issues[0]->severity)->toBe(IssueSeverity::HIGH)
        ->and($issues[0]->table)->toBe('users')
        ->and($issues[0]->column)->toBe('status');
});

// ============================================================
// Spec §9.2 — False Positives
// ============================================================

it('does NOT fire when column has ->default()', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active');
        });
    PHP);

    expect($issues)->toHaveCount(0);
});

it('does NOT fire when column has ->nullable()', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->string('note')->nullable();
        });
    PHP);

    expect($issues)->toHaveCount(0);
});

it('does NOT fire inside Schema::create (table is new)', function () {
    $issues = analyse(<<<'PHP'
        Schema::create('users', function (Blueprint $table) {
            $table->string('required_field');
        });
    PHP);

    expect($issues)->toHaveCount(0);
});

it('detects unsafe integer column', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('payments', function (Blueprint $table) {
            $table->integer('amount');
        });
    PHP);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->column)->toBe('amount');
});
