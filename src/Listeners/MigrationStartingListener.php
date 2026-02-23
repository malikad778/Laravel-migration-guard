<?php

namespace Malikad778\MigrationGuard\Listeners;

use Illuminate\Database\Events\MigrationStarting;
use Illuminate\Support\Facades\Config;
use Malikad778\MigrationGuard\Issues\IssueSeverity;
use Malikad778\MigrationGuard\MigrationAnalyser;
use Symfony\Component\Console\Output\ConsoleOutput;

class MigrationStartingListener
{
    public function __construct(
        protected MigrationAnalyser $analyser
    ) {}

    public function handle(MigrationStarting $event): void
    {
        $migration = $event->migration;

        if (!is_object($migration)) {
            return;
        }

        
        $reflector = new \ReflectionClass($migration);
        $filePath  = $reflector->getFileName();

        if (!$filePath || !is_readable($filePath)) {
            return;
        }

        $issues = $this->analyser->analyseFile($filePath);

        if (empty($issues)) {
            return;
        }

        $mode        = Config::get('migration-guard.mode', 'warn');
        $hasBreaking = false;
        $out         = new ConsoleOutput();

        
        $filename = basename($filePath);
        $out->writeln('');
        $out->writeln('  <bg=red;fg=white;options=bold> MIGRATION GUARD WARNING </>');
        $out->writeln("  File: <comment>{$filename}</comment>");
        $out->writeln('');

        foreach ($issues as $issue) {
            if ($issue->severity === IssueSeverity::BREAKING) {
                $hasBreaking = true;
            }

            $tag = match ($issue->severity) {
                IssueSeverity::BREAKING => 'error',
                IssueSeverity::HIGH     => 'comment',
                IssueSeverity::MEDIUM   => 'info',
            };

            $checkLabel = strtoupper(str_replace('_', ' ', $issue->checkId));
            $out->writeln("  <{$tag}>[{$checkLabel}]</{$tag}> {$issue->message}");

            if ($issue->column) {
                $out->writeln("  Column: <comment>{$issue->column}</comment> on table <comment>{$issue->table}</comment>");
            }

            $out->writeln("  <fg=gray>Safe approach:</> {$issue->safeAlternative}");
            $out->writeln('');
        }

        if ($mode === 'warn') {
            $out->writeln('  <question>Continue anyway? [y/N]</question>');

            $handle  = fopen('php://stdin', 'r');
            $answer  = trim(fgets($handle));
            fclose($handle);

            if (strtolower($answer) !== 'y') {
                $out->writeln('  <error>Migration aborted.</error>');
                throw new \Malikad778\MigrationGuard\Exceptions\MigrationGuardException('Migration aborted by user after warning.');
            }
            
            
            $this->notifyBypass($issues, $filePath);
        } elseif ($mode === 'block') {
            $out->writeln('  <error>Migration blocked. Set MIGRATION_GUARD_MODE=warn to proceed.</error>');
            throw new \Malikad778\MigrationGuard\Exceptions\MigrationGuardException('Migration blocked by laravel-migration-guard.');
        }
    }

    private function notifyBypass(array $issues, string $filePath): void
    {
        
        if (app()->environment('local', 'testing')) {
            return;
        }

        if (!class_exists(\Illuminate\Support\Facades\Notification::class)) {
            return;
        }

        $mailTo = Config::get('migration-guard.notifications.mail.to');
        $slackWebhook = Config::get('migration-guard.notifications.slack.webhook');

        if (!$mailTo && !$slackWebhook) {
            return;
        }

        $route = \Illuminate\Support\Facades\Notification::route('mail', $mailTo);
        if ($slackWebhook) {
            $route->route('slack', $slackWebhook);
        }

        $route->notify(new \Malikad778\MigrationGuard\Notifications\DangerousMigrationBypassed(
            $issues,
            $filePath,
            app()->environment(),
            get_current_user() ?: 'cli'
        ));
    }
}
