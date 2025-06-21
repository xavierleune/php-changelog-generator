# GitHub Secrets Configuration

For the CI/CD workflows to function correctly, you need to configure the following secrets in your GitHub repository:

## Required Secrets

### For Packagist (workflow `packagist.yml`)

1. **`PACKAGIST_USERNAME`**
   - Your Packagist username
   - Go to: Settings > Secrets and variables > Actions > New repository secret

2. **`PACKAGIST_TOKEN`**
   - Your Packagist API token
   - Get it from: https://packagist.org/profile/
   - Section "API Token" > Generate

### For GitHub releases (workflow `release.yml`)

1. **`GITHUB_TOKEN`**
   - Token automatically provided by GitHub
   - No configuration needed

## Secret Configuration

1. Go to your GitHub repository
2. Click on **Settings**
3. In the left menu, click on **Secrets and variables** > **Actions**
4. Click on **New repository secret**
5. Add each secret with the exact name and corresponding value

## Workflows

### CI (`ci.yml`)
- Triggers on push/PR to `main` and `develop`
- Runs tests on PHP 8.3 and 8.4
- Checks code standards and static analysis
- Generates code coverage report

### Packagist (`packagist.yml`)
- Triggers when new tags are created
- Automatically updates the package on Packagist

### Release (`release.yml`)
- Triggers when tags starting with `v` are created (e.g., `v1.0.0`)
- Generates a PHAR file
- Creates a GitHub release with the PHAR attached
- Generates a SHA256 hash of the PHAR

## Workflow Usage

### To create a release

```bash
# Create and push a tag
git tag v1.0.0
git push origin v1.0.0
```

This will automatically trigger:
1. The update on Packagist
2. PHAR generation and GitHub release creation