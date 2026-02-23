<div align="center">

# 🛡️ laravel-migration-guard

**Catch dangerous database migrations before they reach production.**

The `strong_migrations` equivalent for Laravel. Static analysis. Zero configuration. Framework-native.

[![Tests](https://img.shields.io/github/actions/workflow/status/malikad778/Laravel-migration-guard/tests.yml?branch=main&label=tests&style=flat-square&logo=github)](https://github.com/malikad778/Laravel-migration-guard/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-8892BF?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/malikad778/laravel-migration-guard?style=flat-square&logo=packagist&logoColor=white)](https://packagist.org/packages/malikad778/laravel-migration-guard)
[![Total Downloads](https://img.shields.io/packagist/dt/malikad778/laravel-migration-guard?style=flat-square&logo=packagist&logoColor=white&color=4CAF50)](https://packagist.org/packages/malikad778/laravel-migration-guard)
[![License](https://img.shields.io/github/license/malikad778/Laravel-migration-guard?style=flat-square&color=16A085)](LICENSE.md)
[![Stars](https://img.shields.io/github/stars/malikad778/Laravel-migration-guard?style=flat-square&logo=github&color=f1c40f)](https://github.com/malikad778/Laravel-migration-guard/stargazers)
[![Issues](https://img.shields.io/github/issues/malikad778/Laravel-migration-guard?style=flat-square&logo=github)](https://github.com/malikad778/Laravel-migration-guard/issues)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen?style=flat-square)](https://github.com/malikad778/Laravel-migration-guard/pulls)
[![Pest](https://img.shields.io/badge/tested%20with-Pest-A259FF?style=flat-square&logo=pestphp&logoColor=white)](https://pestphp.com)

</div>

---
![Demo](https://raw.githubusercontent.com/malikad778/Laravel-migration-guard/main/demo.gif)

## The Problem

Every Laravel team doing zero-downtime deployments has eventually had a migration incident. These operations **succeed without errors in development**, then cause production outages anywhere from immediately to hours later:

| Operation | What breaks in production |
|---|---|
| `dropColumn()` | Old app instances still query the dropped column — immediate DB errors during the deployment window |
| `NOT NULL` without default | Full table rewrite on MySQL < 8.0 — locks reads **and** writes for minutes on large tables |
| `renameColumn()` | Old instances use old name, new instances use new name — one of them is always wrong |
| `addIndex()` without `INPLACE` | MySQL < 8.0 holds a full write lock while building the index — minutes on busy tables |
| `change()` column type | Full table rewrite, potential silent data truncation (e.g. `VARCHAR(50)` → `VARCHAR(40)`) |
| `Schema::rename()` | Every Eloquent model and raw query referencing the old table name breaks immediately |
| `truncate()` in a migration | Production data permanently destroyed — migrations are the wrong place for data deletion |

Rails developers have had [`strong_migrations`](https://github.com/ankane/strong_migrations) (4,000+ GitHub stars) for years. **The Laravel ecosystem has no maintained equivalent.** Every team solves this by hand: code review checklists, tribal knowledge, and hoping nobody forgets to check.

`laravel-migration-guard` eliminates that risk by making `artisan migrate` production-aware — without changing your workflow.

---

## Installation

```bash
composer require --dev malikad778/laravel-migration-guard
```

The package auto-discovers via Laravel's package discovery. No manual registration required.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=migration-guard-config
```

**That's it.** Out of the box, with zero configuration, the guard:

- ✅ Hooks into `artisan migrate` and warns before any dangerous migration runs
- ✅ Is active only when `APP_ENV` is `production` or `staging`
- ✅ Is completely silent in `local` and `testing` environments
- ✅ Outputs warnings inline before execution, allowing you to abort with `Ctrl+C`

---

## How It Works

The package uses **static analysis** — it parses your migration PHP files into an Abstract Syntax Tree (AST) using [`nikic/php-parser`](https://github.com/nikic/PHP-Parser) and walks the tree looking for dangerous method call patterns.

This means:

- **No database connection needed** — analysis works against raw PHP files in any environment, including CI/CD pipelines
- **Sub-millisecond per file** — PHP AST parsing is extremely fast; 200 migration files takes under a second
- **Only the `up()` method is analysed** — `down()` rollbacks are intentionally excluded
- **`Schema::create()` is excluded** — creating a fresh table with no existing rows is always safe; only `Schema::table()` operations are checked

### Analysis Pipeline

```
Migration file
      ↓
  PHP-Parser AST
      ↓
  Extract up() method body
      ↓
  Walk AST nodes (Schema::table / Schema::create context tracked)
      ↓
  Run registered check visitors
      ↓
  Collect Issue objects (severity, table, column, message, safe alternative)
      ↓
  Console / JSON / GitHub Annotation reporter
```

---

## Safety Checks

Nine checks are included. All enabled by default, individually configurable.

| Check ID | Severity | What It Detects |
|---|---|---|
| `drop_column` | 🔴 BREAKING | `dropColumn()` or `dropColumns()` on an existing table |
| `drop_table` | 🔴 BREAKING | `Schema::drop()` or `Schema::dropIfExists()` |
| `rename_column` | 🔴 BREAKING | `renameColumn()` on any table |
| `rename_table` | 🔴 BREAKING | `Schema::rename()` |
| `modify_primary_key` | 🔴 BREAKING | `dropPrimary()` or `primary()` on an existing table |
| `truncate` | 🔴 BREAKING | `DB::table()->truncate()` inside a migration |
| `add_column_not_null` | 🟡 HIGH | Column added without `->nullable()` or `->default()` |
| `change_column_type` | 🟡 HIGH | `->change()` modifying an existing column type |
| `add_index` | 🔵 MEDIUM | Index added to a critical or large table |

---

## Check Details & Safe Alternatives

### Drop Column — BREAKING

```php
// ❌ DANGEROUS
Schema::table('invoices', function (Blueprint $table) {
    $table->dropColumn('amount');
});
```

**Why:** During a zero-downtime deployment, old app instances run alongside the new schema. Any query touching the dropped column fails immediately with a database error.

**Safe approach:**
1. **Deploy 1:** Remove all code references to the column (models, queries, `$fillable`, `$casts`)
2. **Deploy 2:** Drop the column after confirming no running instance references it

---

### Add NOT NULL Column Without Default — HIGH

```php
// ❌ DANGEROUS — locks the table on MySQL < 8.0
Schema::table('users', function (Blueprint $table) {
    $table->string('status');
});

// ✅ SAFE
Schema::table('users', function (Blueprint $table) {
    $table->string('status')->nullable();
});
```

**Why:** MySQL < 8.0 requires a full table rewrite when adding a `NOT NULL` column without a default. On a large table this blocks all reads and writes for minutes.

**Safe approach:**
1. Add the column as `->nullable()` (instant, no lock)
2. Backfill existing rows: `User::whereNull('status')->update(['status' => 'active'])`
3. Add the `NOT NULL` constraint in a separate migration after backfill completes

---

### Rename Column / Table — BREAKING

```php
// ❌ DANGEROUS
Schema::table('users', function (Blueprint $table) {
    $table->renameColumn('name', 'full_name');
});

Schema::rename('users', 'customers');
```

**Why:** Old instances use the old name, new instances use the new name — one is always wrong during the deployment window. Eloquent models, raw queries, and `$fillable` arrays all break.

**Safe approach:** Add new column → copy data → update code → deploy → drop old column in a follow-up migration.

---

### Add Index (on critical/large tables) — MEDIUM

```php
// ⚠️  RISKY on tables with millions of rows
Schema::table('orders', function (Blueprint $table) {
    $table->index('user_id');
});

// ✅ SAFE — use native syntax for online index creation
DB::statement('ALTER TABLE orders ADD INDEX idx_user_id (user_id) ALGORITHM=INPLACE, LOCK=NONE');
```

**Why:** MySQL < 8.0 holds a full write lock while building an index. MySQL 8.0+ and PostgreSQL support online index builds but require specific syntax that Laravel migrations do not use by default.

---

### Change Column Type — HIGH

```php
// ❌ DANGEROUS
Schema::table('users', function (Blueprint $table) {
    $table->string('bio', 100)->change(); // was VARCHAR(255)
});
```

**Why:** A full table rewrite is required in most databases. Implicit type coercions can silently corrupt data (e.g. `VARCHAR(255)` → `VARCHAR(100)` truncates existing values). Indexes on the column may be dropped.

**Safe approach:** Add new column of the correct type → migrate data → update code → deploy → drop old column.

---

## Example Warning Output

```
$ php artisan migrate

Running migrations...

  ┌──────────────────────────────────────────────────────────────┐
  │  MIGRATION GUARD  │  BREAKING                               │
  └──────────────────────────────────────────────────────────────┘
  File   : 2024_01_15_000001_drop_amount_column.php
  Line   : 12
  Check  : drop_column
  Table  : invoices
  Column : amount

  Dropping column 'amount' from 'invoices' is dangerous.
  Running app instances may still query this column.

  Safe approach:
  1. Remove code references to 'amount' in this deployment.
  2. Drop the column in a follow-up deployment.

  Continue anyway? [y/N]
```

---

## Configuration

```php
<?php
// config/migration-guard.php

return [

    // Environments where guard is active.
    // Empty array = always active.
    'environments' => ['production', 'staging'],

    // 'warn'  -> display warning, let developer abort with Ctrl+C
    // 'block' -> throw exception, halt migration immediately
    'mode' => env('MIGRATION_GUARD_MODE', 'warn'),

    // Toggle individual checks on or off.
    'checks' => [
        'drop_column'         => true,
        'drop_table'          => true,
        'rename_column'       => true,
        'rename_table'        => true,
        'add_column_not_null' => true,
        'change_column_type'  => true,
        'add_index'           => true,
        'modify_primary_key'  => true,
        'truncate'            => true,
    ],

    // Tables that always trigger extra scrutiny for index checks.
    'critical_tables' => [
        // 'users', 'orders', 'payments',
    ],

    // Row count threshold for automatic large-table detection (requires live DB connection).
    'row_threshold' => env('MIGRATION_GUARD_ROW_THRESHOLD', 500000),

    // Suppress a specific check on a specific table or column.
    // Use after confirming the operation is safe for your situation.
    'ignore' => [
        // ['check' => 'drop_column',         'table' => 'legacy_logs'],
        // ['check' => 'add_column_not_null',  'table' => 'users', 'column' => 'migrated_at'],
    ],

];
```

### Environment Variable Overrides

| Variable | Description |
|---|---|
| `MIGRATION_GUARD_MODE` | `warn` or `block`. Overrides config file. |
| `MIGRATION_GUARD_DISABLE` | Set to `true` to disable entirely (e.g. in CI seed steps). |
| `MIGRATION_GUARD_ROW_THRESHOLD` | Row count above which a table is treated as critical for index checks. Default: `500000`. |

---

## Artisan Commands

### `php artisan migration:guard:analyse`

Standalone command for CI/CD pipelines. Analyses all pending migrations and outputs a report **without running them**. Exits with code `1` if dangerous operations are found.

```bash
# Analyse all pending migrations (default)
php artisan migration:guard:analyse

# JSON output — for GitLab Code Quality or custom tooling
php artisan migration:guard:analyse --format=json

# GitHub Actions annotations — inline PR diff comments
php artisan migration:guard:analyse --format=github

# Control when CI fails
php artisan migration:guard:analyse --fail-on=breaking   # default
php artisan migration:guard:analyse --fail-on=high       # BREAKING + HIGH
php artisan migration:guard:analyse --fail-on=any        # all severities
php artisan migration:guard:analyse --fail-on=none       # never fail (report only)

# Analyse all migrations, not just pending
php artisan migration:guard:analyse --pending-only=false

# Analyse a specific file or directory
php artisan migration:guard:analyse --path=database/migrations/2024_01_15_drop_column.php
```

**Exit codes:**

| Code | Meaning |
|---|---|
| `0` | No issues found, or all issues below `--fail-on` threshold |
| `1` | One or more issues at or above threshold |
| `2` | Analysis error (parse failure, permission error) |

---

### `php artisan migration:guard:ignore`

Adds a suppression entry to `config/migration-guard.php` for a specific check and table — or check, table, and column.

```bash
# Suppress an entire table for a check
php artisan migration:guard:ignore drop_column legacy_logs
# → Added: ignore drop_column on table 'legacy_logs'

# Suppress a specific column on a specific table
php artisan migration:guard:ignore add_column_not_null users migrated_at
# → Added: ignore add_column_not_null on table 'users' column 'migrated_at'
```

Valid check IDs: `drop_column`, `drop_table`, `rename_column`, `rename_table`, `add_column_not_null`, `change_column_type`, `add_index`, `modify_primary_key`, `truncate`

---

### JSON Output Schema

```json
[
  {
    "check": "drop_column",
    "severity": "breaking",
    "file": "2024_01_15_000001_drop_amount_column.php",
    "file_path": "/var/www/database/migrations/2024_01_15_000001_drop_amount_column.php",
    "line": 12,
    "table": "invoices",
    "column": "amount",
    "message": "Dropping column 'amount' from 'invoices' is dangerous.",
    "safe_alternative": "Remove code references first. Drop in a follow-up deployment."
  }
]
```

---

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/migration-guard.yml
name: Migration Safety Check

on: [pull_request]

jobs:
  migration-guard:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - run: composer install --no-interaction --prefer-dist

      - run: php artisan migration:guard:analyse --format=github --fail-on=breaking
```

The `--format=github` flag produces GitHub Actions annotation syntax, placing inline warnings **directly on the pull request diff** at the relevant migration file line.

---

### GitLab CI

```yaml
migration-guard:
  stage: test
  script:
    - composer install --no-interaction
    - php artisan migration:guard:analyse --format=json > migration-guard-report.json
  artifacts:
    reports:
      codequality: migration-guard-report.json
```

---

## Package Architecture

```
src/
├── Checks/
│   ├── CheckInterface.php
│   ├── AbstractCheck.php             ← isIgnored(), extractColumnsFromArgs() helpers
│   ├── DropColumnCheck.php
│   ├── DropTableCheck.php
│   ├── RenameColumnCheck.php
│   ├── RenameTableCheck.php
│   ├── AddColumnNotNullCheck.php
│   ├── ChangeColumnTypeCheck.php
│   ├── AddIndexCheck.php             ← live DB row count query (v1.1.0+)
│   ├── ModifyPrimaryKeyCheck.php
│   └── TruncateCheck.php
├── Issues/
│   ├── Issue.php                     ← readonly DTO: checkId, severity, table, column…
│   └── IssueSeverity.php             ← enum: BREAKING | HIGH | MEDIUM
├── Reporters/
│   ├── ReporterInterface.php
│   ├── ConsoleReporter.php
│   ├── JsonReporter.php
│   └── GithubAnnotationReporter.php
├── Commands/
│   ├── AnalyseCommand.php            ← migration:guard:analyse
│   ├── IgnoreCommand.php             ← migration:guard:ignore
│   ├── DigestCommand.php             ← migration:guard:digest  (v1.2.0)
│   └── FixCommand.php                ← migration:guard:fix     (v2.0.0)
├── Listeners/
│   └── MigrationStartingListener.php
├── MigrationAnalyser.php             ← core: parse → traverse → collect issues
├── MigrationNodeVisitor.php          ← AST visitor: tracks Schema::table context
├── MigrationContext.php              ← current table name + isCreate flag
└── MigrationGuardServiceProvider.php
```

---

## Requirements

| | Version |
|---|---|
| PHP | 8.2 or higher |
| Laravel | 10.x, 11.x, 12.x |
| MySQL | 5.7+ or 8.0+ |
| PostgreSQL | 13+ |
| SQLite | 3+ |
| `nikic/php-parser` | ^5.0 *(installed automatically)* |

---

## Comparison: `strong_migrations` vs `laravel-migration-guard`

| Feature | strong_migrations | laravel-migration-guard |
|---|:---:|:---:|
| Drop column detection | ✅ | ✅ |
| Drop table detection | ✅ | ✅ |
| Rename detection | ✅ | ✅ |
| NOT NULL without default | ✅ | ✅ |
| Index safety | ✅ | ✅ |
| CI/CD JSON output | ✅ | ✅ |
| GitHub Annotations | ❌ | ✅ |
| Per-table suppression | ✅ | ✅ |
| **Per-column suppression** | ❌ | ✅ |
| Warn vs Block mode | ✅ | ✅ |
| Zero config defaults | ✅ | ✅ |
| Framework | Rails only | Laravel only |

---

## Roadmap

**v1.0.0 — Launch** *(current)*
- All 9 checks fully implemented and tested
- `artisan migrate` hook
- `migration:guard:analyse` with table, JSON, GitHub output
- `migration:guard:ignore` command
- Full documentation

**v1.1.0 — Database Awareness**
- Query the live database to get actual row counts for index safety thresholds
- Show estimated lock duration based on table size
- PostgreSQL-specific checks: `CONCURRENTLY` index builds, `VACUUM` considerations

**v1.2.0 — Reporting**
- Weekly migration safety digest: summary of all migrations run in the past 7 days
- Slack / email notification when dangerous migrations are bypassed in production
- Audit log of every migration run with who triggered it

**v2.0.0 — Safe Alternative Code Generation**
- For each detected issue, generate the safe equivalent migration stub automatically
- `migration:guard:fix` command that rewrites the migration file with the safe pattern

---

## Contributing

Contributions are welcome. Adding a new check requires only:

1. Create a class implementing `CheckInterface` in `src/Checks/`
2. Register it in `MigrationGuardServiceProvider::register()`
3. Add the check ID to the `checks` array in `config/migration-guard.php`
4. Write unit tests covering both the unsafe pattern and the safe equivalent (false positive tests are required)

```bash
# Run the test suite
./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage
```

---

## License

MIT — free forever. See [LICENSE.md](LICENSE.md).

---

<div align="center">

Made for the Laravel ecosystem · Inspired by [`strong_migrations`](https://github.com/ankane/strong_migrations)

**[Report a Bug](https://github.com/malikad778/laravel-migration-guard/issues) · [Request a Feature](https://github.com/malikad778/laravel-migration-guard/issues) · [Sponsor](https://github.com/sponsors/malikad778)**

</div>
