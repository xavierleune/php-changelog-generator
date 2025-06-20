# PHP Changelog Generator

Un outil PHP pour détecter automatiquement les changements d'API entre deux versions d'un projet PHP et générer un changelog respectant les principes SemVer.

## Fonctionnalités

- ✅ Analyse des éléments publics uniquement (classes, interfaces, méthodes, fonctions, constantes)
- ✅ Support des signatures PHP natives et PHPDoc 
- ✅ Détection des changements compatibles/incompatibles
- ✅ Recommandations SemVer automatiques (majeure/mineure/patch)
- ✅ Génération de changelog Markdown
- ✅ Support des patterns d'exclusion
- ✅ Compatible PHP 7.4+ pour l'analyse, PHP 8.3+ pour l'exécution

## Installation

```bash
composer install
```

## Utilisation

### Commande de base

```bash
./bin/changelog-generator /path/to/old/version /path/to/new/version
```

### Options disponibles

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

### Exemples

```bash
# Générer un changelog basique
./bin/changelog-generator ./v1.0.0 ./v1.1.0

# Spécifier la version actuelle et le fichier de sortie
./bin/changelog-generator ./v1.0.0 ./v1.1.0 -v 1.0.0 -o CHANGELOG.md

# Ignorer des dossiers spécifiques
./bin/changelog-generator ./v1.0.0 ./v1.1.0 -i "*/vendor/*" -i "*/tests/*" -i "*/examples/*"

# Générer un rapport JSON
./bin/changelog-generator ./v1.0.0 ./v1.1.0 -f json -o report.json

# Prévisualiser sans écrire de fichier
./bin/changelog-generator ./v1.0.0 ./v1.1.0 --dry-run
```

## Règles SemVer

### Changements **Majeurs** (Breaking Changes)
- Méthodes/fonctions supprimées
- Signatures incompatibles (paramètres obligatoires ajoutés, types modifiés)
- Classes/interfaces supprimées
- Constantes modifiées/supprimées
- Changements de visibilité restrictifs

### Changements **Mineurs** (Backward Compatible)
- Nouvelles méthodes/fonctions/classes
- Paramètres optionnels ajoutés
- Nouvelles interfaces implémentées
- Nouvelles constantes

### Changements **Patch**
- Modifications de PHPDoc sans impact sur la signature
- Changements internes sans impact sur l'API publique

## Analyse PHPDoc

L'outil privilégie l'analyse des signatures PHP natives, mais utilise la PHPDoc comme fallback :

- Si la signature PHP est typée → utilise la signature
- Si seule la PHPDoc est typée → utilise la PHPDoc  
- En cas de conflit → log un avertissement et utilise la signature

## Architecture

```
src/
├── Model/          # Modèles de données (ApiElement, ApiChange, etc.)
├── Parser/         # Analyseur PHP (nikic/php-parser + PHPDoc)
├── Differ/         # Comparateur de versions
├── Analyzer/       # Analyse SemVer
├── Generator/      # Générateur de changelog
└── Console/        # Interface CLI
```

## Développement

### Tests

```bash
composer test
```

### Standards de code

```bash
composer cs-check  # Vérifier
composer cs-fix    # Corriger
```

### Analyse statique

```bash
composer phpstan
```

## Licence

MIT