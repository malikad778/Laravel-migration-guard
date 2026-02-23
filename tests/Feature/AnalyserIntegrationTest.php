<?php

namespace Malikad778\MigrationGuard\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Malikad778\MigrationGuard\Checks\AddIndexCheck;
use Malikad778\MigrationGuard\MigrationAnalyser;
use Malikad778\MigrationGuard\MigrationContext;
use Malikad778\MigrationGuard\Tests\TestCase;

/**
 * Integration tests — spec §9.3
 * Uses real SQLite in-memory database via Orchestra Testbench.
 */
class AnalyserIntegrationTest extends TestCase
{
    public function test_analyser_resolves_from_container(): void
    {
        $analyser = $this->app->make(MigrationAnalyser::class);
        $this->assertInstanceOf(MigrationAnalyser::class, $analyser);
    }

    public function test_add_index_does_not_fire_for_non_critical_table(): void
    {
        Config::set('migration-guard.critical_tables', ['orders']);

        $code = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        Schema::table('logs', function ($table) {
            $table->index('user_id');
        });
    }
    public function down(): void {}
};
PHP;
        $analyser = new MigrationAnalyser();
        $analyser->addCheck(new AddIndexCheck());
        $issues = $analyser->analyseCode($code, new MigrationContext('test.php'));
        $this->assertCount(0, $issues);
    }

    public function test_add_index_fires_for_critical_table(): void
    {
        Config::set('migration-guard.critical_tables', ['orders']);

        $code = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function ($table) {
            $table->index('user_id');
        });
    }
    public function down(): void {}
};
PHP;
        $analyser = new MigrationAnalyser();
        $analyser->addCheck(new AddIndexCheck());
        $issues = $analyser->analyseCode($code, new MigrationContext('test.php'));
        $this->assertCount(1, $issues);
        $this->assertSame('add_index', $issues[0]->checkId);
    }

    public function test_disabled_check_produces_no_issues(): void
    {
        Config::set('migration-guard.checks.drop_column', false);

        $code = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function ($table) {
            $table->dropColumn('email');
        });
    }
    public function down(): void {}
};
PHP;
        $analyser = $this->app->make(MigrationAnalyser::class);
        $issues   = $analyser->analyseCode($code, new MigrationContext('test.php'));
        $dropColumnIssues = array_filter($issues, fn($i) => $i->checkId === 'drop_column');
        $this->assertCount(0, $dropColumnIssues);
    }
}
