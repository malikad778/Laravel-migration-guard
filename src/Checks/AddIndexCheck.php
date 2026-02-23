<?php

namespace Malikad778\MigrationGuard\Checks;

use Illuminate\Support\Facades\Config;
use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

class AddIndexCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'add_index';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        $issues = [];

        
        if ($context->isCreate || $context->table === null) {
            return [];
        }

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->toString();

            if (in_array($methodName, ['index', 'unique', 'fullText', 'spatialIndex'], true)) {
                
                $criticalTables = Config::get('migration-guard.critical_tables', []);
                $isCritical = in_array($context->table, $criticalTables, true);
                
                
                
                if (!$isCritical && class_exists(\Illuminate\Support\Facades\DB::class)) {
                    try {
                        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
                        $count  = 0;

                        if ($driver === 'mysql') {
                            $result = \Illuminate\Support\Facades\DB::selectOne(
                                'SELECT table_rows as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                                [$context->table]
                            );
                            $count = $result->count ?? 0;
                        } elseif ($driver === 'pgsql') {
                            $result = \Illuminate\Support\Facades\DB::selectOne(
                                'SELECT reltuples::bigint AS count FROM pg_class WHERE relname = ?',
                                [$context->table]
                            );
                            $count = $result->count ?? 0;
                        }

                        $threshold = Config::get('migration-guard.row_threshold', 500_000);
                        if ($count > $threshold) {
                            $isCritical = true;
                        }
                    } catch (\Exception) {
                        
                    }
                }

                if ($isCritical && !$this->isIgnored($context->table)) {
                    $column = $this->extractColumnName($node);

                    $issues[] = new Issue(
                        checkId: $this->id(),
                        severity: IssueSeverity::MEDIUM,
                        migrationFile: $context->migrationFile,
                        table: $context->table,
                        column: $column,
                        message: "Adding an index to critical table '{$context->table}' may lock writes or cause performance degradation.",
                        safeAlternative: "MySQL < 8.0: Locks the table. MySQL 8.0+/Postgres: Use native raw queries with ALGORITHM=INPLACE or CONCURRENTLY.",
                        line: $node->getStartLine()
                    );
                }
            }
        }

        return $issues;
    }

    private function extractColumnName(MethodCall $node): string
    {
        
        if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
            return $node->args[0]->value->value;
        }

        if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Expr\Array_) {
            return implode(',', $this->extractColumnsFromArgs($node->args));
        }

        
        
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
