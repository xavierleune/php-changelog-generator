<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Leune\ChangelogGenerator\Model\ApiSnapshot;

class PhpParser
{
    private Parser $parser;
    private PhpDocParser $phpDocParser;

    public function __construct()
    {
        $factory = new ParserFactory();
        $this->parser = $factory->createForHostVersion();
        $this->phpDocParser = new PhpDocParser();
    }

    public function parseDirectory(string $path, array $ignorePatterns = []): ApiSnapshot
    {
        $snapshot = new ApiSnapshot();
        $visitor = new ApiVisitor($snapshot, $this->phpDocParser);
        $basePath = rtrim(realpath($path), '/') . '/';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$this->shouldProcessFile($file, $ignorePatterns)) {
                continue;
            }

            $filePath = $file->getPathname();
            $relativePath = $this->getRelativePath($filePath, $basePath);
            $checksum = $this->calculateChecksum($filePath);

            if ($checksum !== null) {
                $snapshot->addFileChecksum($relativePath, $checksum);
            }

            $this->parseFile($filePath, $visitor, $relativePath);
        }

        return $snapshot;
    }

    private function getRelativePath(string $filePath, string $basePath): string
    {
        $realPath = realpath($filePath);
        if ($realPath && str_starts_with($realPath, $basePath)) {
            return substr($realPath, strlen($basePath));
        }
        return $filePath;
    }

    private function calculateChecksum(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        return md5($content);
    }

    private function shouldProcessFile(SplFileInfo $file, array $ignorePatterns): bool
    {
        if ($file->getExtension() !== 'php') {
            return false;
        }

        $path = $file->getPathname();
        foreach ($ignorePatterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return false;
            }
        }

        return true;
    }

    private function parseFile(string $filePath, ApiVisitor $visitor, string $relativePath): void
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        try {
            $ast = $this->parser->parse($code);
            if ($ast === null) {
                return;
            }

            $visitor->setCurrentFile($relativePath);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);
        } catch (\Throwable $e) {
            var_dump($e->getTraceAsString());
            error_log("Failed to parse file {$filePath}: " . $e->getMessage());
        }
    }
}
