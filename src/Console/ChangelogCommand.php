<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Leune\ChangelogGenerator\Analyzer\SemVerAnalyzer;
use Leune\ChangelogGenerator\Differ\ApiDiffer;
use Leune\ChangelogGenerator\Differ\FileChecksumComparer;
use Leune\ChangelogGenerator\Generator\ChangelogGenerator;
use Leune\ChangelogGenerator\Parser\PhpParser;
use Throwable;

class ChangelogCommand extends Command
{
    protected static string $defaultName = 'changelog:generate';
    protected static string $defaultDescription = 'Generate changelog by comparing two PHP codebases';

    private PhpParser $parser;
    private ApiDiffer $differ;
    private FileChecksumComparer $fileComparer;
    private SemVerAnalyzer $semVerAnalyzer;
    private ChangelogGenerator $generator;

    public function __construct()
    {
        parent::__construct();
        $this->parser = new PhpParser();
        $this->differ = new ApiDiffer();
        $this->fileComparer = new FileChecksumComparer();
        $this->semVerAnalyzer = new SemVerAnalyzer();
        $this->generator = new ChangelogGenerator();
    }

    protected function configure(): void
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription)
            ->addArgument('old-path', InputArgument::REQUIRED, 'Path to the old version of the codebase')
            ->addArgument('new-path', InputArgument::REQUIRED, 'Path to the new version of the codebase')
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output file for the changelog (relative from new-path)',
                'CHANGELOG.md'
            )
            ->addOption('current-version', 'c', InputOption::VALUE_OPTIONAL, 'Current version number', '1.0.0')
            ->addOption(
                'ignore',
                'i',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Patterns to ignore (supports wildcards)',
                ['*/vendor/*', '*/tests/*', '*/test/*']
            )
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (markdown, json)', 'markdown')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without writing to file')
            ->addOption(
                'no-empty-changeset',
                null,
                InputOption::VALUE_NONE,
                'If no changes are detected, do not generate a changelog entry and recommend the same version'
            )
            ->addOption(
                'strict-semver',
                null,
                InputOption::VALUE_NONE,
                'Use strict SemVer rules (breaking changes = major even for pre-1.0.0)'
            )
            ->setHelp('This command compares two PHP codebases and generates a changelog with SemVer recommendations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $quiet = $output->isQuiet();
        $io = new SymfonyStyle($input, $output);

        $oldPath = $input->getArgument('old-path');
        if (!str_starts_with($oldPath, '/')) {
            $oldPath = getcwd() . '/' . $oldPath;
        }
        $newPath = $input->getArgument('new-path');
        if (!str_starts_with($newPath, '/')) {
            $newPath = getcwd() . '/' . $newPath;
        }
        $outputFile = $newPath . '/' . $input->getOption('output');
        $currentVersion = $input->getOption('current-version');
        $ignorePatterns = $input->getOption('ignore');
        $format = $input->getOption('format');
        $dryRun = $input->getOption('dry-run');
        $strictSemver = $input->getOption('strict-semver');

        if (!is_dir($oldPath)) {
            $output->writeln(
                "<error>Old path does not exist or is not a directory: $oldPath</error>",
                OutputInterface::VERBOSITY_QUIET
            );
            return Command::FAILURE;
        }

        if (!is_dir($newPath)) {
            $output->writeln(
                "<error>New path does not exist or is not a directory: $newPath</error>",
                OutputInterface::VERBOSITY_QUIET
            );
            return Command::FAILURE;
        }

        if (!$quiet) {
            $io->title('PHP Changelog Generator');
            $io->section('Analyzing codebases...');
        }

        try {
            if (!$quiet) {
                $io->text('Parsing old codebase...');
            }
            $oldSnapshot = $this->parser->parseDirectory($oldPath, $ignorePatterns);
            
            if (!$quiet) {
                $io->text('Parsing new codebase...');
            }
            $newSnapshot = $this->parser->parseDirectory($newPath, $ignorePatterns);
            
            if (!$quiet) {
                $io->text('Comparing versions...');
            }
            $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

            if (!$quiet) {
                $io->text('Comparing file checksums...');
            }
            $fileChanges = $this->fileComparer->compare($oldSnapshot, $newSnapshot, $changes);

            $recommendedVersion = $this->semVerAnalyzer->getRecommendedVersion(
                $currentVersion,
                $changes,
                $strictSemver
            );

            if (empty($changes) && !empty($fileChanges)) {
                $recommendedVersion = $this->bumpPatch($currentVersion);
            }

            if (empty($changes) && empty($fileChanges) && $input->getOption('no-empty-changeset')) {
                $recommendedVersion = $currentVersion;
            }

            $severity = $this->semVerAnalyzer->analyzeSeverity($changes, $currentVersion, $strictSemver);
            if (empty($changes) && !empty($fileChanges)) {
                $severity = 'patch';
            }

            if (empty($changes) && empty($fileChanges)) {
                $this->displayNoChangesMessage($io, $quiet, $currentVersion, $recommendedVersion);
            } elseif (empty($changes) && count($fileChanges) > 0) {
                $this->displayInternalChangesOnly($io, $quiet, $fileChanges, $currentVersion, $recommendedVersion);
            } else {
                $this->displayChangesAnalysis(
                    $io,
                    $quiet,
                    $changes,
                    $fileChanges,
                    $currentVersion,
                    $recommendedVersion,
                    $severity
                );
            }

            if (!empty($changes) || !empty($fileChanges) || !$input->getOption('no-empty-changeset')) {
                $this->generateOutput(
                    $format,
                    $dryRun,
                    $quiet,
                    $changes,
                    $fileChanges,
                    $recommendedVersion,
                    $currentVersion,
                    $severity,
                    $outputFile,
                    $io
                );
            }
            if ($quiet) {
                $output->writeln($recommendedVersion, OutputInterface::VERBOSITY_QUIET);
            }
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $output->writeln(
                '<error>An error occured: ' . $e->getMessage() . '</error>',
                OutputInterface::VERBOSITY_QUIET
            );

            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function bumpPatch(string $currentVersion): string
    {
        $versionParts = explode('.', $currentVersion);
        $major = (int) ($versionParts[0] ?? 0);
        $minor = (int) ($versionParts[1] ?? 0);
        $patch = (int) ($versionParts[2] ?? 0);
        $patch++;
        return "{$major}.{$minor}.{$patch}";
    }

    private function groupChangesByType(array $changes): array
    {
        $grouped = ['major' => [], 'minor' => [], 'patch' => []];

        foreach ($changes as $change) {
            $severity = $change->getSeverity();
            if (isset($grouped[$severity])) {
                $grouped[$severity][] = $change;
            }
        }

        return $grouped;
    }

    private function serializeChanges(array $changes): array
    {
        return array_map(function ($change) {
            return [
                'type' => $change->getType(),
                'severity' => $change->getSeverity(),
                'description' => $change->getDescription(),
                'element' => [
                    'type' => $change->getElement()->getType(),
                    'name' => $change->getElement()->getName(),
                    'namespace' => $change->getElement()->getNamespace(),
                    'fqn' => $change->getElement()->getFullyQualifiedName(),
                ],
            ];
        }, $changes);
    }

    private function displayNoChangesMessage(
        SymfonyStyle $io,
        bool $quiet,
        string $currentVersion,
        string $recommendedVersion
    ): void {
        if (!$quiet) {
            $io->success('No API changes detected between versions.');
            $io->definitionList(
                ['Current Version' => $currentVersion],
                ['Recommended Version' => $recommendedVersion],
                ['Severity' => 'Patch']
            );
        }
    }

    private function displayInternalChangesOnly(
        SymfonyStyle $io,
        bool $quiet,
        array $fileChanges,
        string $currentVersion,
        string $recommendedVersion
    ): void {
        if (!$quiet) {
            $io->section('Change Analysis');
            $io->text(sprintf(
                'No API changes detected, but %d file(s) have internal modifications',
                count($fileChanges)
            ));

            $io->definitionList(
                ['Current Version' => $currentVersion],
                ['Recommended Version' => $recommendedVersion],
                ['Severity' => 'Patch']
            );

            $io->text('📝 Files with internal changes:');
            foreach ($fileChanges as $fileChange) {
                $io->text(sprintf('   - %s', $fileChange->getRelativePath()));
            }
        }
    }

    private function displayChangesAnalysis(
        SymfonyStyle $io,
        bool $quiet,
        array $changes,
        array $fileChanges,
        string $currentVersion,
        string $recommendedVersion,
        string $severity
    ): void {
        if (!$quiet) {
            $io->section('Change Analysis');
            $totalChanges = count($changes) + count($fileChanges);
            $io->text(sprintf('Found %d changes (%d API, %d internal)', $totalChanges, count($changes), count($fileChanges)));

            $io->definitionList(
                ['Current Version' => $currentVersion],
                ['Recommended Version' => $recommendedVersion],
                ['Severity' => ucfirst($severity)]
            );

            $changesByType = $this->groupChangesByType($changes);

            if (!empty($changesByType['major'])) {
                $io->warning(sprintf('⚠️  %d BREAKING changes detected', count($changesByType['major'])));
            }

            if (!empty($changesByType['minor'])) {
                $io->note(sprintf('ℹ️  %d new features added', count($changesByType['minor'])));
            }

            if (!empty($changesByType['patch'])) {
                $io->text(sprintf('✅ %d patch-level changes', count($changesByType['patch'])));
            }

            if (!empty($fileChanges)) {
                $io->text(sprintf('📝 %d internal file changes (no API modification)', count($fileChanges)));
            }
        }
    }

    private function generateOutput(
        string $format,
        bool $dryRun,
        bool $quiet,
        array $changes,
        array $fileChanges,
        string $recommendedVersion,
        string $currentVersion,
        string $severity,
        string $outputFile,
        SymfonyStyle $io
    ): void {
        if ($format === 'markdown') {
            $this->generateMarkdownOutput($dryRun, $quiet, $changes, $fileChanges, $recommendedVersion, $outputFile, $io);
        } elseif ($format === 'json') {
            $this->generateJsonOutput(
                $dryRun,
                $quiet,
                $changes,
                $fileChanges,
                $recommendedVersion,
                $currentVersion,
                $severity,
                $outputFile,
                $io
            );
        }
    }

    private function generateMarkdownOutput(
        bool $dryRun,
        bool $quiet,
        array $changes,
        array $fileChanges,
        string $recommendedVersion,
        string $outputFile,
        SymfonyStyle $io
    ): void {
        if ($dryRun) {
            if (!$quiet) {
                $changelog = $this->generator->generate($changes, $recommendedVersion, null, $fileChanges);
                $io->section('Generated Changelog');
                $io->text($changelog);
            }
        } else {
            $changelog = $this->generator->generateForFile($changes, $recommendedVersion, $outputFile, null, $fileChanges);

            file_put_contents($outputFile, $changelog);
            if (!$quiet) {
                $io->success("Changelog written to: $outputFile");
            }
        }
    }

    private function generateJsonOutput(
        bool $dryRun,
        bool $quiet,
        array $changes,
        array $fileChanges,
        string $recommendedVersion,
        string $currentVersion,
        string $severity,
        string $outputFile,
        SymfonyStyle $io
    ): void {
        $data = [
            'currentVersion' => $currentVersion,
            'recommendedVersion' => $recommendedVersion,
            'severity' => $severity,
            'changes' => $this->serializeChanges($changes),
            'fileChanges' => $this->serializeFileChanges($fileChanges),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($dryRun) {
            if (!$quiet) {
                $io->section('Generated JSON');
                $io->text($json);
            }
        } else {
            file_put_contents($outputFile, $json);
            if (!$quiet) {
                $io->success("JSON report written to: $outputFile");
            }
        }
    }

    private function serializeFileChanges(array $fileChanges): array
    {
        return array_map(function ($fileChange) {
            return [
                'path' => $fileChange->getRelativePath(),
                'oldChecksum' => $fileChange->getOldChecksum(),
                'newChecksum' => $fileChange->getNewChecksum(),
            ];
        }, $fileChanges);
    }
}
