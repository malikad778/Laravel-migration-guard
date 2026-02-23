<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;

interface CheckInterface
{
    public function id(): string;

    

    public function analyse(Node $node, MigrationContext $context): array;
}
