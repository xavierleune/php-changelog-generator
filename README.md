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