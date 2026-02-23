<?php

namespace Malikad778\MigrationGuard\Reporters;

use Illuminate\Console\Command;
use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;

class ConsoleReporter implements ReporterInterface
{
    public function __construct(
        private readonly Command $command
    ) {}

    


    public function report(array $issues): void
    {
        $width = 63;
        $border = '+' . str_repeat('-', $width) . '+';

        foreach ($issues as $issue) {
            $severityLabel = strtoupper($issue->severity->value);

            $color = match ($issue->severity) {
                IssueSeverity::BREAKING => 'red',
                IssueSeverity::HIGH     => 'yellow',
                IssueSeverity::MEDIUM   => 'cyan',
            };

            
            $this->command->line('');
            $this->command->line("  {$border}");
            $this->command->line("  |  <options=bold>MIGRATION GUARD</options=bold>  |  <fg={$color};options=bold>{$severityLabel}</>  " . str_repeat(' ', max(0, $width - 22 - strlen($severityLabel))) . '|');
            $this->command->line("  {$border}");

            
            $filename = basename($issue->migrationFile);
            $this->command->line("  File  : <comment>{$filename}</comment>");
            $this->command->line("  Line  : {$issue->line}");
            $this->command->line("  Check : {$issue->checkId}");
            $this->command->line("  Table : {$issue->table}");

            if ($issue->column) {
                $this->command->line("  Column: {$issue->column}");
            }

            $this->command->line('');
            $this->command->line("  {$issue->message}");
            $this->command->line('');
            $this->command->line('  <fg=green>Safe approach:</>');

            
            foreach (explode("\n", $issue->safeAlternative) as $line) {
                $this->command->line("  <fg=gray>{$line}</>");
            }

            $this->command->line('');
        }
    }
}
