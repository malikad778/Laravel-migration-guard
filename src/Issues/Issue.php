<?php

namespace Malikad778\MigrationGuard\Issues;

readonly class Issue
{
    public function __construct(
        public string        $checkId,
        public IssueSeverity $severity,
        public string        $migrationFile,
        public ?string       $table,
        public ?string       $column,
        public string        $message,
        public string        $safeAlternative,
        public int           $line,
    ) {}
}
