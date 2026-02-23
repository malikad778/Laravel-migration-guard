<?php

use Malikad778\MigrationGuard\Issues\IssueSeverity;

// DropTableCheck
it('detects Schema::drop as BREAKING', function () {
    $issues = analyse("Schema::drop('users');");
    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('drop_table')
        ->and($issues[0]->severity)->toBe(IssueSeverity::BREAKING)
        ->and($issues[0]->table)->toBe('users');
});

it('detects Schema::dropIfExists as BREAKING', function () {
    $issues = analyse("Schema::dropIfExists('orders');");
    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('drop_table')
        ->and($issues[0]->table)->toBe('orders');
});

// RenameTableCheck
it('detects Schema::rename as BREAKING', function () {
    $issues = analyse("Schema::rename('users', 'customers');");
    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('rename_table')
        ->and($issues[0]->severity)->toBe(IssueSeverity::BREAKING)
        ->and($issues[0]->table)->toBe('users');
});

// RenameColumnCheck
it('detects renameColumn as BREAKING', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        });
    PHP);
    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('rename_column')
        ->and($issues[0]->severity)->toBe(IssueSeverity::BREAKING)
        ->and($issues[0]->table)->toBe('users')
        ->and($issues[0]->column)->toBe('name');
});

// ChangeColumnTypeCheck
it('detects ->change() as HIGH', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 100)->change();
        });
    PHP);
    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('change_column_type')
        ->and($issues[0]->severity)->toBe(IssueSeverity::HIGH);
});

it('does NOT detect ->change() inside Schema::create', function () {
    $issues = analyse(<<<'PHP'
        Schema::create('users', function (Blueprint $table) {
            $table->string('email')->change();
        });
    PHP);
    expect(collect($issues)->where('checkId', 'change_column_type'))->toHaveCount(0);
});

// ModifyPrimaryKeyCheck
it('detects ->dropPrimary() as BREAKING', function () {
    $issues = analyse(<<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->dropPrimary();
        });
    PHP);
    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('modify_primary_key')
        ->and($issues[0]->severity)->toBe(IssueSeverity::BREAKING);
});

// TruncateCheck
it('detects DB::table()->truncate() as BREAKING', function () {
    $issues = analyse("DB::table('payments')->truncate();");
    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('truncate')
        ->and($issues[0]->severity)->toBe(IssueSeverity::BREAKING)
        ->and($issues[0]->table)->toBe('payments');
});
