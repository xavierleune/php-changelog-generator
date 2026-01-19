<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Model;

class FileChange
{
    public function __construct(
        private string $relativePath,
        private string $oldChecksum,
        private string $newChecksum
    ) {
    }

    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    public function getOldChecksum(): string
    {
        return $this->oldChecksum;
    }

    public function getNewChecksum(): string
    {
        return $this->newChecksum;
    }
}
