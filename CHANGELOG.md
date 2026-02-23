# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] — 2024-02-22

### Added
- **9 migration safety checks**: `drop_column`, `drop_table`, `rename_column`, `rename_table`, `add_column_not_null`, `change_column_type`, `add_index`, `modify_primary_key`, `truncate`
- **`migration:guard:analyse`** artisan command — static analysis of pending migrations with `table`, `json`, and `github` output formats. Supports `--fail-on`, `--pending-only`, `--path` options.
- **`migration:guard:ignore`** artisan command — writes suppression entries directly to `config/migration-guard.php`
- **`migration:guard:fix`** artisan command (v2.0.0 preview) — shows safe alternative for each detected issue
- **`migration:guard:digest`** artisan command (v1.2.0 preview) — summarises migrations run in the last N days
- **`MigrationStartingListener`** — intercepts `php artisan migrate` in real-time via Laravel's `MigrationStarting` event. Displays warnings and prompts the developer to continue or abort.
- **ConsoleReporter** — boxed ANSI output with severity badge, file, line, check, table, column, message, and safe approach
- **JsonReporter** — machine-readable JSON output with all issue fields including `safe_alternative`
- **GithubAnnotationReporter** — native GitHub Actions annotations (`::error` / `::warning`) for PR-level comments
- **`DangerousMigrationBypassed` notification** (v1.2.0 preview) — Laravel notification for Slack/Email when a dangerous migration is bypassed
- **`row_threshold` config** — mark tables as critical when row count exceeds threshold
- **`ignore` config** — suppress specific checks on specific tables (or specific columns)
- **`MIGRATION_GUARD_DISABLE`** environment variable — bypass the guard entirely
- **`MIGRATION_GUARD_MODE`** environment variable — override warn/block mode at runtime
- Full test suite: 35 tests, 102 assertions, covering all 9 checks + false positive cases + SQLite integration tests
- GitHub Actions CI matrix: PHP 8.2/8.3/8.4 × Laravel 10/11/12

### Security
- Guard only activates in configured environments (default: `production`, `staging`)
- Uses static AST analysis via `nikic/php-parser` — no code execution required
