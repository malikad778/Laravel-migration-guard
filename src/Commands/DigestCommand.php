<?php

namespace Malikad778\MigrationGuard\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;



class DigestCommand extends Command
{
    protected $signature = 'migration:guard:digest
                            {--days=7 : Number of past days to include in the digest}
                            {--format=table : Output format (table, json)}';

    protected $description = 'Generate a summary of migrations run recently, highlighting any bypassed dangerous ones.';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $format = $this->option('format');

        $this->info("Migration Guard Digest — last {$days} day(s)\n");

        
        if (!DB::getSchemaBuilder()->hasTable('migrations')) {
            $this->error('No migrations table found. Run php artisan migrate first.');
            return Command::FAILURE;
        }

        
        $latestBatch = DB::table('migrations')->max('batch') ?? 0;
        $startBatch  = max(1, $latestBatch - $days + 1); 

        $migrations = DB::table('migrations')
            ->where('batch', '>=', $startBatch)
            ->orderBy('batch')
            ->orderBy('migration')
            ->get(['migration', 'batch']);

        if ($migrations->isEmpty()) {
            $this->line('  No migrations found in the last ' . $days . ' day(s).');
            return Command::SUCCESS;
        }

        $this->line("  Found <comment>{$migrations->count()}</comment> migration(s) in the last {$days} day(s).\n");

        if ($format === 'json') {
            $this->line(json_encode($migrations->toArray(), JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $headers = ['Migration', 'Batch'];
        $rows    = $migrations->map(fn($m) => [$m->migration, $m->batch])->toArray();
        $this->table($headers, $rows);

        $this->newLine();
        $this->line('<fg=gray>Tip: Install the v1.2.0 reporting add-on for Slack/email alerts when dangerous migrations ran in production.</>');

        return Command::SUCCESS;
    }
}
