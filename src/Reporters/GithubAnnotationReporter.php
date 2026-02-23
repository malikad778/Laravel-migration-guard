<?php

namespace Malikad778\MigrationGuard\Reporters;

use Illuminate\Console\Command;
use Malikad778\MigrationGuard\Issues\IssueSeverity;



class GithubAnnotationReporter implements ReporterInterface
{
    public function __construct(
        private readonly Command $command
    ) {}

    


    public function report(array $issues): void
    {
        foreach ($issues as $issue) {
            $level = match($issue->severity) {
                IssueSeverity::BREAKING => 'error',
                IssueSeverity::HIGH     => 'warning',
                IssueSeverity::MEDIUM   => 'notice',
            };

            $title   = strtoupper(str_replace('_', ' ', $issue->checkId));
            $message = addslashes($issue->message);

            
            $this->command->line(
                "::{$level} file={$issue->migrationFile},line={$issue->line},title={$title}::{$message}"
            );
        }
    }
}
