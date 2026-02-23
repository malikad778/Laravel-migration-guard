<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

class DropTableCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'drop_table';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        $issues = [];

        
        if ($node instanceof StaticCall) {
            if ($node->class instanceof Node\Name && $node->class->toString() === 'Schema') {
                if ($node->name instanceof Node\Identifier) {
                    $methodName = $node->name->toString();

                    if (in_array($methodName, ['drop', 'dropIfExists'], true)) {
                        $table = null;
                        if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                            $table = $node->args[0]->value->value;
                        }

                        if ($table && !$this->isIgnored($table)) {
                            $issues[] = new Issue(
                                checkId: $this->id(),
                                severity: IssueSeverity::BREAKING,
                                migrationFile: $context->migrationFile,
                                table: $table,
                                column: null,
                                message: "Dropping table '{$table}' is dangerous.",
                                safeAlternative: "Remove code references to the table in the first deployment. Drop the table in a follow-up deployment.",
                                line: $node->getStartLine()
                            );
                        }
                    }
                }
            }
        }

        return $issues;
    }
}
