<?php

namespace Malikad778\MigrationGuard\Reporters;

use Malikad778\MigrationGuard\Issues\Issue;

interface ReporterInterface
{
    


    public function report(array $issues): void;
}
