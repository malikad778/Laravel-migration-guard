<?php

namespace Malikad778\MigrationGuard\Commands;

use Illuminate\Console\Command;

class IgnoreCommand extends Command
{
    protected $signature = 'migration:guard:ignore {check} {table} {column?}';

    protected $description = 'Add a suppression entry to config/migration-guard.php for a specific check and table combination.';

    public function handle(): int
    {
        $check  = $this->argument('check');
        $table  = $this->argument('table');
        $column = $this->argument('column');

        $configPath = config_path('migration-guard.php');

        
        $validChecks = [
            'drop_column', 'drop_table', 'rename_column', 'rename_table',
            'add_column_not_null', 'change_column_type', 'add_index',
            'modify_primary_key', 'truncate',
        ];

        if (!in_array($check, $validChecks, true)) {
            $this->error("Unknown check ID: '{$check}'. Valid checks are: " . implode(', ', $validChecks));
            return Command::FAILURE;
        }

        if (!file_exists($configPath)) {
            $this->error("Config file not found at {$configPath}. Run: php artisan vendor:publish --tag=migration-guard-config");
            return Command::FAILURE;
        }

        
        if ($column) {
            $entryLine = "        ['check' => '{$check}', 'table' => '{$table}', 'column' => '{$column}'],";
            $success   = "Added: ignore {$check} on table '{$table}' column '{$column}'";
        } else {
            $entryLine = "        ['check' => '{$check}', 'table' => '{$table}'],";
            $success   = "Added: ignore {$check} on table '{$table}'";
        }

        
        $contents = file_get_contents($configPath);

        
        $pattern     = "/('ignore'\s*=>\s*\[)([\s\S]*?)(\s*\],)/";
        $replacement = "$1$2\n{$entryLine}\n    $3";

        $newContents = preg_replace($pattern, $replacement, $contents, 1, $count);

        if ($count === 0 || $newContents === null) {
            $this->error("Could not automatically update config file. Please add manually:");
            $this->line("\n    " . trim($entryLine));
            return Command::FAILURE;
        }

        file_put_contents($configPath, $newContents);

        
        exec("php -l " . escapeshellarg($configPath) . " 2>&1", $output, $returnVar);
        if ($returnVar !== 0) {
            
            file_put_contents($configPath, $contents);
            $this->error("Automatically updating config file failed (produced invalid PHP). Restored original. Please add manually:");
            $this->line("\n    " . trim($entryLine));
            return Command::FAILURE;
        }

        $this->info($success);

        return Command::SUCCESS;
    }
}
