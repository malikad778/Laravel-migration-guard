<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;

class RenameTableCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'rename_table';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        $issues = [];

        
        if ($node instanceof StaticCall) {
            if ($node->class instanceof Node\Name && $node->class->toString() === 'Schema') {
                if ($node->name instanceof Node\Identifier && $node->name->toString() === 'rename') {
                    $tableFrom = null;
                    if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                        $tableFrom = $node->args[0]->value->value;
                    }

                    if ($tableFrom && !$this->isIgnored($tableFrom)) {
                        $issues[] = new Issue(
                            checkId: $this->id(),
                            severity: IssueSeverity::BREAKING,
                            migrationFile: $context->migrationFile,
                            table: $tableFrom,
                            column: null,
                            message: "Renaming table '{$tableFrom}' is dangerous.",
                            safeAlternative: "Create a new table, copy data, update code to use the new table, deploy, then drop the old table in a follow-up migration.",
                            line: $node->getStartLine()
                        );
                    }
                }
            }
        }

        return $issues;
    }
}
