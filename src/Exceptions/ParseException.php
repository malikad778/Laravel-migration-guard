<?php

namespace Malikad778\MigrationGuard\Exceptions;

use PhpParser\Error;

class ParseException extends MigrationGuardException
{
    public function __construct(string $file, Error $previous)
    {
        parent::__construct(
            "Parse error while analysing migration file [{$file}]: " . $previous->getMessage(),
            0,
            $previous
        );
    }
}
