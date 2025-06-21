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
use Leune\ChangelogGenerator\Generator\ChangelogGenerator;
use Leune\ChangelogGenerator\Parser\PhpParser;

class ChangelogCommand extends Command
{
    protected static $defaultName = 'changelog:generate';
    protected static $defaultDescription = 'Generate changelog by comparing two PHP codebases';

    private PhpParser $parser;
    private ApiDiffer $differ;
    private SemVerAnalyzer $semVerAnalyzer;
    private ChangelogGenerator $generator;

    public function __construct()
    {
        parent::__construct();
        $this->parser = new PhpParser();
        $this->differ = new ApiDiffer();
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
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file for the changelog', 'CHANGELOG.md')
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
            ->setHelp('This command compares two PHP codebases and generates a changelog with SemVer recommendations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $oldPath = $input->getArgument('old-path');
        $newPath = $input->getArgument('new-path');
        $outputFile = $input->getOption('output');
        $currentVersion = $input->getOption('current-version');
        $ignorePatterns = $input->getOption('ignore');
        $format = $input->getOption('format');
        $dryRun = $input->getOption('dry-run');

        if (!is_dir($oldPath)) {
            $io->error("Old path does not exist or is not a directory: {$oldPath}");
            return Command::FAILURE;
        }

        if (!is_dir($newPath)) {
            $io->error("New path does not exist or is not a directory: {$newPath}");
            return Command::FAILURE;
        }

        $io->title('PHP Changelog Generator');
        $io->section('Analyzing codebases...');

        try {
            $io->text('Parsing old codebase...');
            $oldSnapshot = $this->parser->parseDirectory($oldPath, $ignorePatterns);
            
            $io->text('Parsing new codebase...');
            $newSnapshot = $this->parser->parseDirectory($newPath, $ignorePatterns);
            
            $io->text('Comparing versions...');
            $changes = $this->differ->diff($oldSnapshot, $newSnapshot);

            if (empty($changes)) {
                $io->success('No API changes detected between versions.');
                return Command::SUCCESS;
            }

            $io->section('Change Analysis');
            $io->text(sprintf('Found %d changes', count($changes)));

            $recommendedVersion = $this->semVerAnalyzer->getRecommendedVersion($currentVersion, $changes);
            $severity = $this->semVerAnalyzer->analyzeSeverity($changes);

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

            if ($format === 'markdown') {
                if ($dryRun) {
                    $changelog = $this->generator->generate($changes, $recommendedVersion);
                    $io->section('Generated Changelog');
                    $io->text($changelog);
                } else {
                    $changelog = $this->generator->generateForFile($changes, $recommendedVersion, $outputFile);
                    file_put_contents($outputFile, $changelog);
                    $io->success("Changelog written to: {$outputFile}");
                }
            } elseif ($format === 'json') {
                $data = [
                    'currentVersion' => $currentVersion,
                    'recommendedVersion' => $recommendedVersion,
                    'severity' => $severity,
                    'changes' => $this->serializeChanges($changes),
                ];
                
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                
                if ($dryRun) {
                    $io->section('Generated JSON');
                    $io->text($json);
                } else {
                    file_put_contents($outputFile, $json);
                    $io->success("JSON report written to: {$outputFile}");
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
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
}
