<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

class TruncateCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'truncate';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        $issues = [];

        
        if ($node instanceof MethodCall) {
            if ($this->isMethodName($node, 'truncate')) {
                
                $isDbTable = false;
                $table = 'unknown_table';

                $current = $node->var;
                while ($current instanceof MethodCall) {
                    $current = $current->var;
                }

                if ($current instanceof StaticCall) {
                    $staticCall = $current;
                    if ($staticCall->class instanceof Node\Name && $staticCall->class->toString() === 'DB') {
                        if ($staticCall->name instanceof Node\Identifier && $staticCall->name->toString() === 'table') {
                            $isDbTable = true;
                            if (isset($staticCall->args[0]) && $staticCall->args[0]->value instanceof Node\Scalar\String_) {
                                $table = $staticCall->args[0]->value->value;
                            }
                        }
                    }
                }

                if ($isDbTable && !$this->isIgnored($table)) {
                    $issues[] = new Issue(
                        checkId: $this->id(),
                        severity: IssueSeverity::BREAKING,
                        migrationFile: $context->migrationFile,
                        table: $table,
                        column: null,
                        message: "Truncating table '{$table}' is dangerous.",
                        safeAlternative: "Truncating tables in migrations is generally unsafe for production data; consider deleting via a background job or deleting old records based on timestamp.",
                        line: $node->getStartLine()
                    );
                }
            }
        }

        return $issues;
    }
}
