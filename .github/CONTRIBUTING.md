# Contributing to PHP Changelog Generator

Thank you for your interest in contributing to the project! Here are the guidelines for contributing.

## Development Setup

### Prerequisites

- PHP 8.3 or higher
- Composer

### Installation

```bash
git clone https://github.com/your-username/php-changelog-generator.git
cd php-changelog-generator
composer install
```

## Development

### Available Scripts

```bash
# Run all tests
composer test

# Check code standards
composer cs-check

# Automatically fix code standards
composer cs-fix

# Static analysis with PHPStan
composer phpstan

# Complete CI pipeline (tests + PHPStan + CS)
composer ci

# Generate PHAR
composer build-phar
```

### Tests

We use PHPUnit for unit and integration tests:

```bash
# Unit tests only
./vendor/bin/phpunit tests/Unit

# Integration tests only
./vendor/bin/phpunit tests/Integration

# All tests with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Code Standards

The project uses PSR-2 as the code standard. Make sure your code follows these standards:

```bash
composer cs-check
composer cs-fix
```

### Static Analysis

We use PHPStan for static analysis:

```bash
composer phpstan
```

## CI/CD Workflows

The project uses GitHub Actions for continuous integration:

### Available Workflows

1. **CI** (`.github/workflows/ci.yml`)
   - Tests on PHP 8.3 and 8.4
   - Code standards verification
   - PHPStan static analysis
   - Coverage report generation

2. **Packagist** (`.github/workflows/packagist.yml`)
   - Automatic update on Packagist when tags are created
   - Requires `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` secrets

3. **Release** (`.github/workflows/release.yml`)
   - Automatic PHAR generation
   - GitHub release creation with binaries
   - Triggers on `v*` tags (e.g., `v1.0.0`)

### GitHub Secrets Configuration

For the workflows to function, configure these secrets in your repository:

- `PACKAGIST_USERNAME`: Your Packagist username
- `PACKAGIST_TOKEN`: Your Packagist API token

## Contribution Process

1. **Fork** the repository
2. **Create a branch** for your feature (`git checkout -b feature/my-feature`)
3. **Make your changes** following the standards
4. **Add tests** for your modifications
5. **Verify everything passes** with `composer ci`
6. **Commit** your changes (`git commit -am 'Add: my new feature'`)
7. **Push** to your branch (`git push origin feature/my-feature`)
8. **Open a Pull Request**

## Commit Format

Use a descriptive format for your commits:

- `Add: new functionality`
- `Fix: bug correction`
- `Update: existing improvement`
- `Remove: deletion`
- `Refactor: refactoring without functional changes`

## Release

To create a new release:

1. Update the version in appropriate files
2. Create and push a tag:
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```
3. GitHub workflows will handle the rest

## Project Structure

```
src/
├── Analyzer/       # SemVer analysis of changes
├── Console/        # CLI interface
├── Differ/         # Version comparison
├── Generator/      # Changelog generation
├── Model/          # Data models
└── Parser/         # PHP code analysis

tests/
├── Integration/    # Integration tests
└── Unit/          # Unit tests

.github/
├── workflows/     # GitHub Actions CI/CD
├── CONTRIBUTING.md
└── SECRETS.md
```

## Questions?

Feel free to open an issue for any questions or improvement suggestions!