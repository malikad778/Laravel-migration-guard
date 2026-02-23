<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

class ChangeColumnTypeCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'change_column_type';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        if ($context->isCreate || $context->table === null) {
            return [];
        }

        $issues = [];

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            if ($node->name->toString() === 'change') {
                $column = $this->extractColumnNameFromChain($node);

                if (!$this->isIgnored($context->table, $column)) {
                    $issues[] = new Issue(
                        checkId: $this->id(),
                        severity: IssueSeverity::HIGH,
                        migrationFile: $context->migrationFile,
                        table: $context->table,
                        column: $column,
                        message: "Changing column type for '{$column}' on '{$context->table}' requires a full table rewrite in most databases.",
                        safeAlternative: "Add a new column of the correct type, migrate data, update code, deploy, then drop the old column.",
                        line: $node->getStartLine()
                    );
                }
            }
        }

        return $issues;
    }

    private function extractColumnNameFromChain(MethodCall $node): string
    {
        $current = $node;
        while ($current->var instanceof MethodCall) {
            $current = $current->var;
        }

        if (isset($current->args[0]) && $current->args[0]->value instanceof Node\Scalar\String_) {
            return $current->args[0]->value->value;
        }

        return 'unknown';
    }
}
