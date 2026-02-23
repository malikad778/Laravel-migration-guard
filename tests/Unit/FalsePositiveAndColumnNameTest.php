<?php

use Malikad778\MigrationGuard\Issues\IssueSeverity;

// ── MISSING-05 ──────────────────────────────────────────────────────────────
// ChangeColumnTypeCheck: assert column name is extracted correctly

it('extracts column name correctly from ->change() chain', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 100)->change();
        });
    PHP);
    $changeIssues = array_filter($issues, fn($i) => $i->checkId === 'change_column_type');
    expect($changeIssues)->toHaveCount(1);
    $issue = reset($changeIssues);
    expect($issue->column)->toBe('email');
});

// ── MISSING-06 — false-positive tests ───────────────────────────────────────

// TruncateCheck: chained where() before truncate still detected (BUG-03 regression)
it('detects DB::table()->where()->truncate() as BREAKING', function () {
    $issues = analyse("DB::table('logs')->where('id', '>', 0)->truncate();");
    $truncateIssues = array_filter($issues, fn($i) => $i->checkId === 'truncate');
    expect($truncateIssues)->toHaveCount(1);
    expect(reset($truncateIssues)->table)->toBe('logs');
});

// TruncateCheck: should stay silent for non-DB-table patterns
it('does NOT fire truncate for unrelated truncate call', function () {
    $issues = analyse("\$str = Str::of('foo'); \$value = strlen('truncate');");
    $truncateIssues = array_filter($issues, fn($i) => $i->checkId === 'truncate');
    expect($truncateIssues)->toHaveCount(0);
});

// ModifyPrimaryKeyCheck: ->primary() inside Schema::create should NOT fire
it('does NOT fire modify_primary_key inside Schema::create', function () {
    $issues = analyse(<<<'PHP'
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->primary(['id', 'uuid']);
        });
    PHP);
    $pkIssues = array_filter($issues, fn($i) => $i->checkId === 'modify_primary_key');
    expect($pkIssues)->toHaveCount(0);
});

// ModifyPrimaryKeyCheck: ->primary() on existing table DOES fire (MISSING-02)
it('detects ->primary() on existing table as BREAKING', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('orders', function (Blueprint $table) {
            $table->primary('uuid');
        });
    PHP);
    $pkIssues = array_filter($issues, fn($i) => $i->checkId === 'modify_primary_key');
    expect($pkIssues)->toHaveCount(1);
});

// RenameColumnCheck: config-based ignore should suppress issue
it('does NOT fire rename_column when ignore rule matches table', function () {
    \Illuminate\Support\Facades\Config::set('migration-guard.ignore', [
        ['check' => 'rename_column', 'table' => 'users'],
    ]);

    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        });
    PHP);
    $renameIssues = array_filter($issues, fn($i) => $i->checkId === 'rename_column');
    expect($renameIssues)->toHaveCount(0);
});

// AddColumnNotNullCheck: chained ->nullable() at any position suppresses the check
it('does NOT fire add_column_not_null when nullable() is chained after column type', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->string('bio')->nullable()->index();
        });
    PHP);
    $issues = array_filter($issues, fn($i) => $i->checkId === 'add_column_not_null');
    expect($issues)->toHaveCount(0);
});

it('does NOT fire add_column_not_null when default() is chained after column type', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->integer('score')->default(0);
        });
    PHP);
    $issues = array_filter($issues, fn($i) => $i->checkId === 'add_column_not_null');
    expect($issues)->toHaveCount(0);
});
