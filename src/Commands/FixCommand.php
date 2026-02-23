<?php

namespace Malikad778\MigrationGuard\Commands;

use Illuminate\Console\Command;
use Malikad778\MigrationGuard\MigrationAnalyser;
use Malikad778\MigrationGuard\Issues\IssueSeverity;



class FixCommand extends Command
{
    protected $signature = 'migration:guard:fix
                            {file : Path to the migration file to fix}
                            {--dry-run : Show the safe stub without writing}';

    protected $description = 'Generate a safe alternative stub for a dangerous migration file (v2.0.0 preview).';

    public function handle(MigrationAnalyser $analyser): int
    {
        $file = $this->argument('file');
        $path = base_path($file);

        if (!file_exists($path)) {
            $this->error("File not found: {$file}");
            return Command::FAILURE;
        }

        $issues = $analyser->analyseFile($path);

        if (empty($issues)) {
            $this->info('No dangerous operations detected in this file. No fix needed.');
            return Command::SUCCESS;
        }

        $this->warn("\n  Found " . count($issues) . " issue(s) in " . basename($path));
        $this->newLine();

        foreach ($issues as $issue) {
            $badge = match($issue->severity) {
                IssueSeverity::BREAKING => '<fg=red;options=bold>[BREAKING]</>',
                IssueSeverity::HIGH     => '<fg=yellow;options=bold>[HIGH]</>',
                IssueSeverity::MEDIUM   => '<fg=cyan>[MEDIUM]</>',
            };

            $this->line("  {$badge} <options=bold>{$issue->checkId}</> on <comment>{$issue->table}</comment>");
            $this->line("  <fg=gray>{$issue->message}</>");
            $this->newLine();
            $this->line("  <fg=green>Safe approach:</>");
            foreach (explode("\n", $issue->safeAlternative) as $line) {
                $this->line("  <fg=gray>  {$line}</>");
            }
            $this->newLine();
        }

        $this->line('<fg=yellow;options=bold>  ⚠  Auto-rewriting is planned for v2.0.0.</> For now, use the safe approaches above.');
        $this->line('  <fg=gray>Run: php artisan migration:guard:analyse --format=github for CI/CD integration.</>');
        $this->newLine();

        return Command::SUCCESS;
    }
}
