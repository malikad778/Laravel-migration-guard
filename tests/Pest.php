<?php

use Malikad778\MigrationGuard\MigrationAnalyser;
use Malikad778\MigrationGuard\MigrationContext;
use Malikad778\MigrationGuard\Tests\TestCase;

// Bind all tests to the Orchestra Testbench Laravel application
uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Spec §9.1 helper: parse a raw PHP snippet and run all enabled checks.
 *
 * The snippet is wrapped inside a real anonymous Migration class with an up() method,
 * matching the exact structure that MigrationNodeVisitor expects.
 *
 * Usage exactly as shown in spec §9.1:
 *
 *   $issues = analyse(<<<'PHP'
 *       Schema::table('invoices', function (Blueprint $table) {
 *           $table->dropColumn('amount');
 *       });
 *   PHP);
 *
 * @param string $snippet  Raw PHP schema code (no opening <?php or function wrapper needed)
 */
function analyse(string $snippet): array
{
    // Wrap snippet in a real anonymous migration class so the up() method tracking fires
    $code = <<<PHP
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        {$snippet}
    }

    public function down(): void {}
};
PHP;

    $analyser = new MigrationAnalyser();

    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\DropColumnCheck());
    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\DropTableCheck());
    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\RenameColumnCheck());
    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\RenameTableCheck());
    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\AddColumnNotNullCheck());
    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\ChangeColumnTypeCheck());
    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\AddIndexCheck());
    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\ModifyPrimaryKeyCheck());
    $analyser->addCheck(new \Malikad778\MigrationGuard\Checks\TruncateCheck());

    return $analyser->analyseCode($code, new MigrationContext('test_migration.php'));
}