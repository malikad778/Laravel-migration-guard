<?php

namespace Malikad778\MigrationGuard;

use Malikad778\MigrationGuard\Checks\CheckInterface;
use Malikad778\MigrationGuard\Issues\Issue;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Illuminate\Support\Facades\Config;

class MigrationAnalyser
{
    private Parser $parser;

    
    private array $checks = [];

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function addCheck(CheckInterface $check): self
    {
        $this->checks[] = $check;
        return $this;
    }

    


    public function analyseFile(string $filePath): array
    {
        if (!is_readable($filePath)) {
            return [];
        }

        $code = file_get_contents($filePath);
        return $this->analyseCode($code, new MigrationContext($filePath));
    }

    


    public function analyseCode(string $code, MigrationContext $context): array
    {
        try {
            $ast = $this->parser->parse($code);
        } catch (Error $e) {
            
            throw new \Malikad778\MigrationGuard\Exceptions\ParseException($context->migrationFile, $e);
        }

        if ($ast === null) {
            return [];
        }

        
        $enabledChecks = array_filter($this->checks, function (CheckInterface $check) {
            return Config::get("migration-guard.checks.{$check->id()}", true);
        });

        $visitor = new MigrationNodeVisitor(array_values($enabledChecks), $context);

        $traverser = new NodeTraverser();
        
        $traverser->addVisitor(new \PhpParser\NodeVisitor\ParentConnectingVisitor());
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getIssues();
    }
}
