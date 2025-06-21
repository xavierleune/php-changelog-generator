<?php
/**
 * Build script for creating a PHAR archive
 */

declare(strict_types=1);

$pharFile = 'changelog-generator.phar';
$sourceDir = __DIR__;

// Remove existing PHAR
if (file_exists($pharFile)) {
    unlink($pharFile);
}

// Create PHAR
$phar = new Phar($pharFile);
$phar->setSignatureAlgorithm(Phar::SHA256);

// Build from directory, excluding test directories and other unnecessary files
$phar->buildFromDirectory($sourceDir, '/^(?!.*\/(tests?|Tests?|\.git|\.svn|\.idea|\.vscode|node_modules|build|docs?)\/)/');

// Create executable stub
$stub = <<<'STUB'
#!/usr/bin/env php
<?php
declare(strict_types=1);

Phar::mapPhar('changelog-generator.phar');

require 'phar://changelog-generator.phar/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Leune\ChangelogGenerator\Console\ChangelogCommand;

$application = new Application('PHP Changelog Generator', '1.0.0');
$application->add(new ChangelogCommand());
$application->setDefaultCommand('changelog:generate', true);
$application->run();

__HALT_COMPILER();
STUB;

$phar->setStub($stub);

// Make it executable
chmod($pharFile, 0755);

echo "PHAR created successfully: {$pharFile}\n";
echo "Size: " . number_format(filesize($pharFile) / 1024, 2) . " KB\n";