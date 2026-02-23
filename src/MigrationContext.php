<?php

namespace Malikad778\MigrationGuard;

class MigrationContext
{
    public ?string $table = null;
    public bool $isCreate = false;

    public function __construct(
        public readonly string $migrationFile
    ) {}
}
