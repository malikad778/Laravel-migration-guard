<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

class RenameColumnCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'rename_column';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        $issues = [];

        
        if ($node instanceof MethodCall) {
            if ($this->isMethodName($node, 'renameColumn')) {
                
                
                $table = $context->table ?? 'unknown_table';

                $columnFrom = 'unknown_column';
                if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                    $columnFrom = $node->args[0]->value->value;
                }

                if (!$this->isIgnored($table, $columnFrom)) {
                    $issues[] = new Issue(
                        checkId: $this->id(),
                        severity: IssueSeverity::BREAKING,
                        migrationFile: $context->migrationFile,
                        table: $table,
                        column: $columnFrom,
                        message: "Renaming column '{$columnFrom}' on '{$table}' is dangerous.",
                        safeAlternative: "Add a new column, copy data, update code to use the new column, deploy, then drop the old column in a follow-up migration.",
                        line: $node->getStartLine()
                    );
                }
            }
        }

        return $issues;
    }
}
