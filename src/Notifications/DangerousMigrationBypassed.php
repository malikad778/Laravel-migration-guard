<?php

namespace Malikad778\MigrationGuard\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Malikad778\MigrationGuard\Issues\Issue;
use Malikad778\MigrationGuard\Issues\IssueSeverity;



class DangerousMigrationBypassed extends Notification
{
    use Queueable;

    
    public function __construct(
        private readonly array  $issues,
        private readonly string $migrationFile,
        private readonly string $environment,
        private readonly string $runBy = 'unknown'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'slack'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $filename = basename($this->migrationFile);
        $count    = count($this->issues);
        $breaking = count(array_filter($this->issues, fn($i) => $i->severity === IssueSeverity::BREAKING));

        $mail = (new MailMessage)
            ->subject("[Migration Guard] ⚠ Dangerous migration bypassed in {$this->environment}")
            ->greeting('Migration Guard Alert')
            ->line("A migration with **{$count} dangerous operation(s)** was run in **{$this->environment}**.")
            ->line("**File:** `{$filename}`")
            ->line("**Run by:** {$this->runBy}")
            ->line("**Breaking issues:** {$breaking}");

        foreach ($this->issues as $issue) {
            $mail->line("---")
                 ->line("**Check:** {$issue->checkId}")
                 ->line("**Table:** {$issue->table}" . ($issue->column ? " | **Column:** {$issue->column}" : ''))
                 ->line($issue->message)
                 ->line("*Safe approach:* " . str_replace("\n", " → ", $issue->safeAlternative));
        }

        return $mail->action('View Migration Guard Docs', 'https://github.com/malikad778/laravel-migration-guard')
                    ->line('This notification was sent by laravel-migration-guard.');
    }

    public function toSlack(object $notifiable): \Illuminate\Notifications\Slack\SlackMessage
    {
        $filename = basename($this->migrationFile);
        $count    = count($this->issues);

        $message = (new \Illuminate\Notifications\Slack\SlackMessage)
            ->headerBlock("⚠ Dangerous migration bypassed in {$this->environment}")
            ->sectionBlock(function ($block) use ($filename, $count) {
                $block->text("Migration: *{$filename}*\nIssues: {$count}\nRun by: {$this->runBy}");
            });

        foreach ($this->issues as $issue) {
            $message->sectionBlock(function ($block) use ($issue) {
                $block->text(
                    "*" . strtoupper($issue->severity->value) . "*: {$issue->checkId}\n" .
                    "Table: {$issue->table}" . ($issue->column ? ", Column: {$issue->column}" : '')
                );
            });
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'migration_file' => $this->migrationFile,
            'environment'    => $this->environment,
            'run_by'         => $this->runBy,
            'issues'         => array_map(fn(Issue $i) => [
                'check'    => $i->checkId,
                'severity' => $i->severity->value,
                'table'    => $i->table,
                'column'   => $i->column,
                'message'  => $i->message,
            ], $this->issues),
        ];
    }
}
