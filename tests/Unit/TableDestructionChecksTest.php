<?php

namespace Malikad778\MigrationGuard\Tests\Unit;

use Malikad778\MigrationGuard\Checks\DropTableCheck;
use Malikad778\MigrationGuard\Checks\RenameTableCheck;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationAnalyser;
use Malikad778\MigrationGuard\MigrationContext;

it('detects Schema::drop as BREAKING', function () {
    $code = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::drop('users');
    }
};
PHP;

    $analyser = new MigrationAnalyser();
    $analyser->addCheck(new DropTableCheck());

    $issues = $analyser->analyseCode($code, new MigrationContext('test.php'));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('drop_table')
        ->and($issues[0]->severity)->toBe(IssueSeverity::BREAKING)
        ->and($issues[0]->table)->toBe('users');
});

it('detects Schema::rename as BREAKING', function () {
    $code = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::rename('users', 'customers');
    }
};
PHP;

    $analyser = new MigrationAnalyser();
    $analyser->addCheck(new RenameTableCheck());

    $issues = $analyser->analyseCode($code, new MigrationContext('test.php'));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('rename_table')
        ->and($issues[0]->severity)->toBe(IssueSeverity::BREAKING)
        ->and($issues[0]->table)->toBe('users');
});
