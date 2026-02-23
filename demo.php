<?php

/**
 * ============================================================
 * laravel-migration-guard — Live Demo Script
 * ============================================================
 * Run this from the package root:
 *   php demo.php
 *
 * Simulates what a developer sees when `php artisan migrate`
 * encounters dangerous migrations and the guard intercepts them.
 * ============================================================
 */

require __DIR__ . '/vendor/autoload.php';

use Malikad778\MigrationGuard\MigrationAnalyser;
use Malikad778\MigrationGuard\MigrationContext;
use Malikad778\MigrationGuard\Issues\IssueSeverity;

// ─── Bootstrap just enough config for the analyser ────────────────────────────
// The guard is pure static analysis — no DB connection needed to analyse files.

// Fake the Illuminate Config facade with our config values
$app = new \Illuminate\Container\Container();
$app->singleton('config', fn() => new \Illuminate\Config\Repository([
    'migration-guard' => require __DIR__ . '/config/migration-guard.php',
]));
\Illuminate\Container\Container::setInstance($app);
\Illuminate\Support\Facades\Facade::setFacadeApplication($app);

// ─── Build the analyser with all 9 checks ─────────────────────────────────────

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

// ─── ANSI colour helpers ──────────────────────────────────────────────────────

$supportsColor = DIRECTORY_SEPARATOR !== '\\' || getenv('ANSICON') || getenv('ConEmuANSI') === 'ON' || getenv('TERM_PROGRAM') === 'vscode';

function c(string $text, string $code): string {
    return "\033[{$code}m{$text}\033[0m";
}
function red(string $t): string      { return c($t, '31;1'); }
function yellow(string $t): string   { return c($t, '33;1'); }
function cyan(string $t): string     { return c($t, '36;1'); }
function green(string $t): string    { return c($t, '32;1'); }
function gray(string $t): string     { return c($t, '90'); }
function bold(string $t): string     { return c($t, '1'); }
function bg_red(string $t): string   { return c($t, '41;97;1'); }
function bg_green(string $t): string { return c($t, '42;30;1'); }
function bg_yellow(string $t): string{ return c($t, '43;30;1'); }
function bg_cyan(string $t): string  { return c($t, '46;30;1'); }
function dim(string $t): string      { return c($t, '2'); }

// ─── Migration scenarios ──────────────────────────────────────────────────────

$migrations = [
    [
        'file'   => 'tests/database/migrations/2024_01_01_000001_create_users_table.php',
        'action' => 'safe',
    ],
    [
        'file'   => 'tests/database/migrations/2024_01_01_000002_create_orders_table.php',
        'action' => 'safe',
    ],
    [
        'file'         => 'tests/database/stubs/dangerous_drop_column.php',
        'action'       => 'abort',      // developer pressed N
    ],
    [
        'file'         => 'tests/database/stubs/dangerous_add_not_null_column.php',
        'action'       => 'continue',   // developer pressed Y
    ],
    [
        'file'         => 'tests/database/stubs/dangerous_rename_column.php',
        'action'       => 'abort',
    ],
    [
        'file'         => 'tests/database/stubs/dangerous_drop_table.php',
        'action'       => 'continue',
    ],
    [
        'file'         => 'tests/database/stubs/dangerous_change_column_type.php',
        'action'       => 'abort',
    ],
    [
        'file'         => 'tests/database/stubs/dangerous_truncate.php',
        'action'       => 'abort',
    ],
];

// ─── Render the artisan migrate header ───────────────────────────────────────

echo "\n";
echo dim('  $ ') . bold('php artisan migrate') . "\n\n";
echo dim('  INFO  Running migrations.') . "\n\n";

$ran = 0;
$aborted = 0;
$overridden = 0;

foreach ($migrations as $migration) {
    $path     = __DIR__ . '/' . $migration['file'];
    $filename = basename($path);

    if (!file_exists($path)) {
        echo gray("  [skip] {$filename} not found\n");
        continue;
    }

    $issues = $analyser->analyseFile($path);

    // ── Safe migration ────────────────────────────────────────────────────────
    if (empty($issues)) {
        echo green('  ✓') . dim(' ' . str_pad($filename, 55, '.') . ' ') . green('DONE') . "\n";
        $ran++;
        continue;
    }

    // ── Dangerous migration — Guard fires ─────────────────────────────────────

    $width  = 65;
    $border = '+' . str_repeat('─', $width) . '+';

    echo "\n";
    echo "  " . bg_red("  MIGRATION GUARD  ") . "  " . bold(dim("File: {$filename}")) . "\n";
    echo "  " . c(str_repeat('─', $width), '31') . "\n";

    foreach ($issues as $issue) {
        $badge = match($issue->severity) {
            IssueSeverity::BREAKING => bg_red(' BREAKING '),
            IssueSeverity::HIGH     => bg_yellow(' HIGH     '),
            IssueSeverity::MEDIUM   => bg_cyan(' MEDIUM   '),
        };

        $checkLine = strtoupper(str_replace('_', ' ', $issue->checkId));

        echo "  {$badge}  " . bold($checkLine) . "\n";
        echo "\n";

        if ($issue->table) {
            $meta = gray('  Table  : ') . bold($issue->table);
            if ($issue->column) {
                $meta .= gray('   Column : ') . bold($issue->column);
            }
            $meta .= gray('   Line : ') . $issue->line;
            echo $meta . "\n";
        }

        echo "\n";
        // Word-wrap the message
        foreach (explode("\n", wordwrap($issue->message, 60, "\n", true)) as $line) {
            echo "  " . $line . "\n";
        }

        echo "\n";
        echo "  " . green('Safe approach:') . "\n";
        foreach (explode("\n", $issue->safeAlternative) as $safeLine) {
            echo gray("  {$safeLine}") . "\n";
        }
        echo "\n";
    }

    echo "  " . c(str_repeat('─', $width), '31') . "\n";

    // ── Interactive prompt simulation ────────────────────────────────────────
    $action = $migration['action'];

    if ($action === 'continue') {
        echo "\n  " . c('Continue anyway? [y/N]', '33') . "  " . c('y', '32;1') . c('  ← developer typed y', '90') . "\n";
        echo "  " . bg_yellow(' WARNING ') . c("  Migration executed despite risks.", '33') . "\n\n";
        $overridden++;
    } else {
        echo "\n  " . c('Continue anyway? [y/N]', '33') . "  " . c('N', '31;1') . c('  ← developer typed N (or hit Enter)', '90') . "\n";
        echo "  " . bg_red(' ABORTED ') . c("  Migration stopped by developer.", '31') . "\n\n";
        $aborted++;
    }
}

// ─── Final summary ────────────────────────────────────────────────────────────

echo "\n  " . c(str_repeat('━', 65), '90') . "\n\n";
echo "  " . bg_green(' SUMMARY ') . "\n\n";
echo "  " . green("✓  {$ran}") . gray(" migration(s) ran cleanly\n");

if ($overridden > 0) {
    echo "  " . yellow("⚠  {$overridden}") . gray(" dangerous migration(s) continued with override\n");
}
if ($aborted > 0) {
    echo "  " . red("✗  {$aborted}") . gray(" migration(s) halted by guard\n");
}

echo "\n";
echo dim("  Tip: Set MIGRATION_GUARD_MODE=block to stop dangerous migrations\n");
echo dim("       without prompting. Set MIGRATION_GUARD_DISABLE=true to bypass.\n");
echo "\n";

// ─── Show what the CI command looks like ─────────────────────────────────────

echo "\n";
echo bold("  ── CI/CD: php artisan migration:guard:analyse ──────────────────\n\n");

$allIssues = [];
foreach ($migrations as $m) {
    $path = __DIR__ . '/' . $m['file'];
    if (file_exists($path)) {
        $issues = $analyser->analyseFile($path);
        foreach ($issues as $issue) {
            // Wrap in array since Issue is readonly (PHP 8.2 no dynamic props)
            $allIssues[] = ['issue' => $issue, 'filename' => basename($path)];
        }
    }
}

if (empty($allIssues)) {
    echo green("  ✓  No dangerous migrations detected.\n\n");
} else {
    $headers = ['Severity', 'Check', 'Table', 'Column', 'File', 'Line'];
    $rows = [];
    foreach ($allIssues as $wrapped) {
        $i = $wrapped['issue'];
        $rows[] = [
            strtoupper($i->severity->value),
            $i->checkId,
            $i->table ?? '-',
            $i->column ?? '-',
            $wrapped['filename'],
            (string)$i->line,
        ];
    }

    // Calculate column widths
    $widths = array_map('strlen', $headers);
    foreach ($rows as $row) {
        foreach ($row as $col => $val) {
            $widths[$col] = max($widths[$col], strlen($val));
        }
    }

    $printRow = function(array $cells, array $widths, ?string $colorFn = null) {
        $out = '  │ ';
        foreach ($cells as $i => $cell) {
            $padded = str_pad($cell, $widths[$i]);
            if ($i === 0 && $colorFn) {
                $padded = match($cell) {
                    'BREAKING' => bg_red(" {$padded} "),
                    'HIGH'     => bg_yellow(" {$padded} "),
                    'MEDIUM'   => bg_cyan(" {$padded} "),
                    default    => $padded,
                };
                $out .= $padded . ' │ ';
            } else {
                $out .= $padded . ' │ ';
            }
        }
        return rtrim($out) . "\n";
    };

    $sepLine = '  ├─' . implode('─┼─', array_map(fn($w) => str_repeat('─', $w), $widths)) . "─┤\n";
    $topLine = '  ┌─' . implode('─┬─', array_map(fn($w) => str_repeat('─', $w), $widths)) . "─┐\n";
    $botLine = '  └─' . implode('─┴─', array_map(fn($w) => str_repeat('─', $w), $widths)) . "─┘\n";

    echo $topLine;
    echo $printRow($headers, $widths);
    echo $sepLine;
    foreach ($rows as $row) {
        echo $printRow($row, $widths, 'severity');
    }
    echo $botLine;

    echo "\n  " . red("Exit code: 1") . gray("  (--fail-on=breaking threshold met)\n");
    echo "\n";
}
