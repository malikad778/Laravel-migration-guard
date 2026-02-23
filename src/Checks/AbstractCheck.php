<?php

namespace Malikad778\MigrationGuard\Checks;

use Illuminate\Support\Facades\Config;
use Malikad778\MigrationGuard\MigrationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Arg;
use PhpParser\Node\Scalar\String_;

abstract class AbstractCheck implements CheckInterface
{
    


    protected function isIgnored(?string $table, ?string $column = null): bool
    {
        $ignores = Config::get('migration-guard.ignore', []);

        foreach ($ignores as $ignore) {
            if ($ignore['check'] === $this->id()) {
                if (isset($ignore['table']) && $ignore['table'] === $table) {
                    if (!isset($ignore['column'])) {
                        return true; 
                    }
                    if ($ignore['column'] === $column) {
                        return true; 
                    }
                }
            }
        }

        return false;
    }


    


    protected function isMethodName(MethodCall $methodCall, array|string $names): bool
    {
        if (!$methodCall->name instanceof Node\Identifier) {
            return false;
        }

        $needle = $methodCall->name->toString();
        $names = (array) $names;

        return in_array($needle, $names, true);
    }

    


    protected function extractColumnsFromArgs(array $args): array
    {
        $columns = [];
        
        foreach ($args as $arg) {
            if ($arg instanceof Arg) {
                if ($arg->value instanceof String_) {
                    $columns[] = $arg->value->value;
                } elseif ($arg->value instanceof Node\Expr\Array_) {
                    foreach ($arg->value->items as $item) {
                        if ($item->value instanceof String_) {
                            $columns[] = $item->value->value;
                        }
                    }
                }
            }
        }

        return $columns;
    }
}
