<?php

namespace Malikad778\MigrationGuard\Checks;

use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

class AddColumnNotNullCheck extends AbstractCheck
{
    


    private array $columnTypes = [
        
        'string', 'char', 'tinyText', 'text', 'mediumText', 'longText',
        
        'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger',
        'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger',
        'unsignedMediumInteger', 'unsignedBigInteger', 'id',
        
        'decimal', 'unsignedDecimal', 'double', 'float',
        
        'date', 'dateTime', 'dateTimeTz', 'time', 'timeTz', 'timestamp', 'timestampTz', 'year',
        
        'boolean', 'binary',
        
        'json', 'jsonb',
        
        'geography', 'geometry', 'lineString', 'multiLineString', 'point', 'multiPoint', 'polygon', 'multiPolygon',
        
        'uuid', 'ulid', 'ipAddress', 'macAddress', 'enum', 'set', 'vector', 'hstore', 'computed',
    ];

    public function id(): string
    {
        return 'add_column_not_null';
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        
        if ($context->isCreate || $context->table === null) {
            return [];
        }

        $issues = [];

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->toString();

            if (in_array($methodName, $this->columnTypes, true)) {
                
                
                $directlyOnTable = !($node->var instanceof MethodCall) 
                    || !($node->var->name instanceof Identifier)
                    || !in_array($node->var->name->toString(), $this->columnTypes, true);

                if (!$directlyOnTable) {
                    return [];
                }

                
                if (!$this->hasNullableOrDefault($node)) {
                    $column = 'unknown';
                    if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                        $column = $node->args[0]->value->value;
                    }

                    if (!$this->isIgnored($context->table, $column)) {
                        $issues[] = new Issue(
                            checkId: $this->id(),
                            severity: IssueSeverity::HIGH,
                            migrationFile: $context->migrationFile,
                            table: $context->table,
                            column: $column,
                            message: "Adding NOT NULL column '{$column}' to '{$context->table}' without a default value requires a full table rewrite on MySQL < 8.0. This locks the table for reads and writes.",
                            safeAlternative: "1. Add the column as ->nullable() in this migration.\n2. Backfill existing rows: Model::whereNull('{$column}')->update(['{$column}' => 'value']);\n3. Add NOT NULL constraint in a follow-up migration.",
                            line: $node->getStartLine()
                        );
                    }
                }
            }
        }

        return $issues;
    }

    


    private function hasNullableOrDefault(MethodCall $innerNode): bool
    {
        $current = $innerNode;
        while ($current = $current->getAttribute('parent')) {
            if ($current instanceof MethodCall) {
                if ($current->name instanceof Identifier) {
                    $name = $current->name->toString();
                    if (in_array($name, ['nullable', 'default', 'useCurrent', 'change'], true)) {
                        return true;
                    }
                }
            } else {
                
                break;
            }
        }
        return false;
    }
}
