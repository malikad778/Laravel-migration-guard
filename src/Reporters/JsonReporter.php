<?php

namespace Malikad778\MigrationGuard\Reporters;

use Illuminate\Console\Command;

class JsonReporter implements ReporterInterface
{
    public function __construct(
        private readonly Command $command
    ) {}

    


    public function report(array $issues): void
    {
        
        $data = array_map(fn($issue) => [
            'check'            => $issue->checkId,
            'severity'         => $issue->severity->value,
            'file'             => basename($issue->migrationFile),
            'file_path'        => $issue->migrationFile,
            'line'             => $issue->line,
            'table'            => $issue->table,
            'column'           => $issue->column,
            'message'          => $issue->message,
            'safe_alternative' => $issue->safeAlternative,
        ], $issues);

        $this->command->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
