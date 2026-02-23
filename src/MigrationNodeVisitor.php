<?php

namespace Malikad778\MigrationGuard;

use Malikad778\MigrationGuard\Checks\CheckInterface;
use Malikad778\MigrationGuard\Issues\Issue;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class MigrationNodeVisitor extends NodeVisitorAbstract
{
    
    private array $issues = [];

    


    private bool $insideUpMethod = false;

    


    private int $upMethodDepth = 0;

    


    public function __construct(
        private readonly array           $checks,
        private readonly MigrationContext $context
    ) {}

    public function enterNode(Node $node)
    {
        
        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($node->name instanceof Node\Identifier && $node->name->toString() === 'up') {
                $this->insideUpMethod = true;
                $this->upMethodDepth  = 0;
            }
            return null;
        }

        
        if (!$this->insideUpMethod) {
            return null;
        }

        
        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\StaticCall) {
            $staticCall = $node->expr;
            if ($staticCall->class instanceof Node\Name && $staticCall->class->toString() === 'Schema') {
                if ($staticCall->name instanceof Node\Identifier) {
                    $methodName = $staticCall->name->toString();
                    if (in_array($methodName, ['table', 'create'], true)) {
                        if (isset($staticCall->args[0]) && $staticCall->args[0]->value instanceof Node\Scalar\String_) {
                            $this->context->table    = $staticCall->args[0]->value->value;
                            $this->context->isCreate = $methodName === 'create';
                        }
                    }
                }
            }
        }

        
        foreach ($this->checks as $check) {
            $newIssues    = $check->analyse($node, $this->context);
            $this->issues = array_merge($this->issues, $newIssues);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        
        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($node->name instanceof Node\Identifier && $node->name->toString() === 'up') {
                $this->insideUpMethod = false;
            }
            return null;
        }

        if (!$this->insideUpMethod) {
            return null;
        }

        
        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\StaticCall) {
            $staticCall = $node->expr;
            if ($staticCall->class instanceof Node\Name && $staticCall->class->toString() === 'Schema') {
                if ($staticCall->name instanceof Node\Identifier &&
                    in_array($staticCall->name->toString(), ['table', 'create'], true)) {
                    $this->context->table    = null;
                    $this->context->isCreate = false;
                }
            }
        }

        return null;
    }

    
    public function getIssues(): array
    {
        return $this->issues;
    }
}
