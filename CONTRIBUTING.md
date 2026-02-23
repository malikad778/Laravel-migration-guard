# Contributing

Thank you for considering contributing to `laravel-migration-guard`! 🎉

## Development Setup

```bash
git clone https://github.com/malikad778/laravel-migration-guard
cd laravel-migration-guard
composer install
```

## Running Tests

```bash
# Run full test suite
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage

# Run only unit tests
vendor/bin/pest tests/Unit

# Run only integration tests
vendor/bin/pest tests/Feature

# Run the live demo
php demo.php
```

## Adding a New Check

1. Create `src/Checks/YourCheck.php` extending `AbstractCheck` and implementing `CheckInterface`
2. Add the check ID to `config/migration-guard.php` under `checks`
3. Register it in `MigrationGuardServiceProvider::register()`
4. Write tests in `tests/Unit/YourCheckTest.php` with positive AND false-positive cases
5. Add a dangerous migration stub to `tests/database/stubs/`

```php
class YourCheck extends AbstractCheck
{
    public function id(): string
    {
        return 'your_check_id'; // matches config key
    }

    public function analyse(Node $node, MigrationContext $context): array
    {
        // Only fire on existing tables unless the danger exists on new ones too
        if ($context->isCreate) {
            return [];
        }

        $issues = [];

        // Detect the dangerous pattern...

        return $issues;
    }
}
```

## Code Style

- PHP 8.2+ features (readonly, enums, match, named args)
- PSR-12 code style
- No `var_dump` / `dd` left in commits
- All public methods must have docblocks if non-obvious

## Pull Request Process

1. Fork the repository
2. Create a branch: `git checkout -b feat/your-check-name`
3. Write your code + tests
4. Ensure `vendor/bin/pest` passes
5. Open a PR — describe the check, why it's dangerous, and what the safe alternative is

## Reporting a False Positive

Open an issue with:
- The migration file snippet (anonymised)
- The check that fired unexpectedly
- Why you believe it is a false positive

## Reporting a Missing Check

Open an issue with:
- Describe the dangerous operation
- Explain why it causes downtime or data loss
- Link to any relevant Rails strong_migrations equivalent if it exists

---

Made with ❤️ — MIT License
