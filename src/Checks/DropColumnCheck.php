<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\Closure;

class DropColumnCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'drop_column';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        $issues = [];

        
        
        if ($context->isCreate || $context->table === null) {
            return [];
        }

        if ($node instanceof MethodCall) {
            if ($this->isMethodName($node, ['dropColumn', 'dropColumns'])) {
                $columns = $this->extractColumnsFromArgs($node->args);

                foreach ($columns as $column) {
                    if ($this->isIgnored($context->table, $column)) {
                        continue;
                    }

                    $issues[] = new Issue(
                        checkId: $this->id(),
                        severity: IssueSeverity::BREAKING,
                        migrationFile: $context->migrationFile,
                        table: $context->table,
                        column: $column,
                        message: "Dropping column '{$column}' from '{$context->table}' is dangerous.",
                        safeAlternative: "Remove code references to the column in the first deployment. Drop the column in a follow-up deployment.",
                        line: $node->getStartLine()
                    );
                }
            }
        }

        return $issues;
    }
}
