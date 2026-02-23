<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

class ModifyPrimaryKeyCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'modify_primary_key';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        $issues = [];

        if ($context->isCreate || $context->table === null) {
            return [];
        }

        if ($node instanceof MethodCall) {
            $isDangerous = false;

            
            if ($this->isMethodName($node, ['dropPrimary', 'primary'])) {
                $isDangerous = true;
            }

            
            if ($this->isMethodName($node, 'change')) {
                $current = $node->var;
                while ($current instanceof MethodCall) {
                    if ($this->isMethodName($current, ['id', 'increments', 'bigIncrements', 'mediumIncrements', 'smallIncrements', 'tinyIncrements'])) {
                        $isDangerous = true;
                        break;
                    }
                    $current = $current->var;
                }
            }

            if ($isDangerous && !$this->isIgnored($context->table)) {
                $issues[] = new Issue(
                    checkId: $this->id(),
                    severity: IssueSeverity::BREAKING,
                    migrationFile: $context->migrationFile,
                    table: $context->table,
                    column: null,
                    message: "Dropping or modifying the primary key on '{$context->table}' is dangerous.",
                    safeAlternative: "Primary key modifications usually require a full table copy or rewrite strategy depending on the database.",
                    line: $node->getStartLine()
                );
            }
        }

        return $issues;
    }
}
