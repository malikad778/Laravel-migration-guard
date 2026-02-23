<?php

namespace Malikad778\MigrationGuard\Tests\Unit;

use Malikad778\MigrationGuard\Checks\AddIndexCheck;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationAnalyser;
use Malikad778\MigrationGuard\MigrationContext;
use Illuminate\Support\Facades\Config;

it('detects an index added to a critical table', function () {
    Config::set('migration-guard.critical_tables', ['orders']);

    $code = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id'); // Unsafe on critical tables
        });
    }
};
PHP;

    $analyser = new MigrationAnalyser();
    $analyser->addCheck(new AddIndexCheck());

    $issues = $analyser->analyseCode($code, new MigrationContext('test.php'));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->checkId)->toBe('add_index')
        ->and($issues[0]->severity)->toBe(IssueSeverity::MEDIUM)
        ->and($issues[0]->table)->toBe('orders')
        ->and($issues[0]->column)->toBe('user_id');
});

it('ignores indexes added to non-critical tables', function () {
    Config::set('migration-guard.critical_tables', ['orders']);

    $code = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->index('user_id'); // Safe on small tables
        });
    }
};
PHP;

    $analyser = new MigrationAnalyser();
    $analyser->addCheck(new AddIndexCheck());

    $issues = $analyser->analyseCode($code, new MigrationContext('test.php'));

    expect($issues)->toHaveCount(0);
});
