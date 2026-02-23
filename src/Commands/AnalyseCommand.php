<?php

namespace Malikad778\MigrationGuard\Commands;

use Illuminate\Console\Command;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationAnalyser;

class AnalyseCommand extends Command
{
    protected $signature = 'migration:guard:analyse
                            {--format=table : Output format (table, json, github)}
                            {--fail-on=breaking : Exit with code 1 on (breaking, high, any, none)}
                            {--all : Analyse all migrations, including those already run}
                            {--path= : Analyse a specific migration file or directory}';

    protected $description = 'Analyses pending migrations for dangerous operations.';

    public function handle(MigrationAnalyser $analyser)
    {
        $format = $this->option('format');
        $failOn = ltrim(strtolower($this->option('fail-on')), '='); 
        $path = $this->option('path');

        $filesToAnalyse = [];

        if ($path) {
            $fullPath = base_path($path);
            if (is_dir($fullPath)) {
                $filesToAnalyse = glob($fullPath . '/*.php');
            } elseif (is_file($fullPath)) {
                $filesToAnalyse = [$fullPath];
            } else {
                $this->error("Path not found: {$path}");
                return 2;
            }
        } else {
            
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles($migrator->paths());
            $analyseAll = $this->option('all');
            
            if (!$analyseAll) {
                $ran = $migrator->getRepository()->getRan();
                $pendingKeys = array_diff(array_keys($files), $ran);
                $filesToAnalyse = array_intersect_key($files, array_flip($pendingKeys));
                $filesToAnalyse = array_values($filesToAnalyse);
            } else {
                $filesToAnalyse = array_values($files);
            }
        }

        $allIssues = [];
        $exitCode  = 0;

        foreach ($filesToAnalyse as $file) {
            try {
                $issues    = $analyser->analyseFile($file);
                $allIssues = array_merge($allIssues, $issues);
            } catch (\Throwable $e) {
                
                $this->error("Parse error in " . basename($file) . ": " . $e->getMessage());
                $exitCode = 2;
            }
        }

        if ($exitCode === 2) {
            return 2;
        }

        if (empty($allIssues)) {
            if ($format === 'table') {
                $this->info('✓  No dangerous migrations detected.');
            } elseif ($format === 'json') {
                $this->line(json_encode([], JSON_PRETTY_PRINT));
            }
            return 0;
        }

        
        
        if ($failOn !== 'none') {
            foreach ($allIssues as $issue) {
                if ($failOn === 'any') {
                    $exitCode = 1;
                    break;
                } elseif ($failOn === 'breaking' && $issue->severity === IssueSeverity::BREAKING) {
                    $exitCode = 1;
                    break;
                } elseif ($failOn === 'high' && in_array($issue->severity, [IssueSeverity::BREAKING, IssueSeverity::HIGH], true)) {
                    $exitCode = 1;
                    break;
                }
            }
        }

        
        $reporter = match ($format) {
            'json'   => new \Malikad778\MigrationGuard\Reporters\JsonReporter($this),
            'github' => new \Malikad778\MigrationGuard\Reporters\GithubAnnotationReporter($this),
            default  => new \Malikad778\MigrationGuard\Reporters\ConsoleReporter($this),
        };

        $reporter->report($allIssues);

        return $exitCode;
    }
}
