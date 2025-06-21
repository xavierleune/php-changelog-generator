# PHP Changelog Generator

A PHP tool to automatically detect API changes between two versions of a PHP project and generate a changelog following SemVer principles.

## Features

- ✅ Analysis of public elements only (classes, interfaces, methods, functions, constants)
- ✅ Support for native PHP signatures and PHPDoc
- ✅ Detection of compatible/incompatible changes
- ✅ Automatic SemVer recommendations (major/minor/patch)
- ✅ Markdown changelog generation
- ✅ Support for exclusion patterns
- ✅ Compatible with PHP 7.4+ for analysis, PHP 8.3+ for execution

## Installation

```bash
composer install
```

## Usage

### Basic command

```bash
./bin/changelog-generator /path/to/old/version /path/to/new/version
```

### Available options

```bash
./bin/changelog-generator old-path new-path [options]

Arguments:
  old-path              Path to the old version of the codebase
  new-path              Path to the new version of the codebase

Options:
  -o, --output=OUTPUT   Output file for the changelog [default: "CHANGELOG.md"]
  -v, --version=VERSION Current version number [default: "1.0.0"]
  -i, --ignore=IGNORE   Patterns to ignore (supports wildcards) [default: ["*/vendor/*", "*/tests/*", "*/test/*"]] (multiple values allowed)
  -f, --format=FORMAT   Output format (markdown, json) [default: "markdown"]
      --dry-run         Show changes without writing to file
      --strict-semver   Use strict SemVer rules (breaking changes = major even for pre-1.0.0)
```

### Examples

```bash
# Generate a basic changelog
./bin/changelog-generator ./v1.0.0 ./v1.1.0

# Specify current version and output file
./bin/changelog-generator ./v1.0.0 ./v1.1.0 -v 1.0.0 -o CHANGELOG.md

# Ignore specific folders
./bin/changelog-generator ./v1.0.0 ./v1.1.0 -i "*/vendor/*" -i "*/tests/*" -i "*/examples/*"

# Generate a JSON report
./bin/changelog-generator ./v1.0.0 ./v1.1.0 -f json -o report.json

# Preview without writing to file
./bin/changelog-generator ./v1.0.0 ./v1.1.0 --dry-run

# Use strict SemVer for pre-1.0.0 versions
./bin/changelog-generator ./v0.1.0 ./v0.2.0 --current-version 0.1.0 --strict-semver
```

## SemVer Rules

### **Major** Changes (Breaking Changes)
- Removed methods/functions
- Incompatible signatures (required parameters added, types changed)
- Removed classes/interfaces
- Modified/removed constants
- Restrictive visibility changes

### **Minor** Changes (Backward Compatible)
- New methods/functions/classes
- Optional parameters added
- New implemented interfaces
- New constants

### **Patch** Changes
- PHPDoc modifications without signature impact
- Internal changes without public API impact

## Pre-1.0.0 SemVer Behavior

**By default**, for versions before 1.0.0 (e.g., 0.x.y), this tool follows a relaxed SemVer approach:

- **Breaking changes** (normally major) are treated as **minor** changes
- **New features** remain **minor** changes  
- **Bug fixes** remain **patch** changes

This reflects the common practice that pre-1.0.0 versions are in development and breaking changes are expected.

### Strict SemVer Mode

Use the `--strict-semver` flag to enforce standard SemVer rules even for pre-1.0.0 versions:

```bash
# Relaxed mode (default): 0.1.0 + breaking changes = 0.2.0
./bin/changelog-generator ./v0.1.0 ./v0.2.0 --current-version 0.1.0

# Strict mode: 0.1.0 + breaking changes = 1.0.0
./bin/changelog-generator ./v0.1.0 ./v0.2.0 --current-version 0.1.0 --strict-semver
```

## PHPDoc Analysis

The tool prioritizes native PHP signature analysis but uses PHPDoc as fallback:

- If PHP signature is typed → uses the signature
- If only PHPDoc is typed → uses PHPDoc
- In case of conflict → logs a warning and uses the signature

## Architecture

```
src/
├── Model/          # Data models (ApiElement, ApiChange, etc.)
├── Parser/         # PHP parser (nikic/php-parser + PHPDoc)
├── Differ/         # Version comparator
├── Analyzer/       # SemVer analysis
├── Generator/      # Changelog generator
└── Console/        # CLI interface
```

## Development

### Tests

```bash
composer test
```

### Code standards

```bash
composer cs-check  # Check
composer cs-fix    # Fix
```

### Static analysis

```bash
composer phpstan
```

## License

MIT