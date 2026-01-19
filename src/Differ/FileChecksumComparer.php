<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Differ;

use Leune\ChangelogGenerator\Model\ApiChange;
use Leune\ChangelogGenerator\Model\ApiSnapshot;
use Leune\ChangelogGenerator\Model\FileChange;

class FileChecksumComparer
{
    /**
     * Compare checksums between two snapshots and return files that changed
     * without having any API changes.
     *
     * @param ApiSnapshot $oldSnapshot
     * @param ApiSnapshot $newSnapshot
     * @param ApiChange[] $apiChanges
     * @return FileChange[]
     */
    public function compare(ApiSnapshot $oldSnapshot, ApiSnapshot $newSnapshot, array $apiChanges): array
    {
        $oldChecksums = $oldSnapshot->getFileChecksums();
        $newChecksums = $newSnapshot->getFileChecksums();

        $filesWithApiChanges = $this->getFilesWithApiChanges($apiChanges);

        $fileChanges = [];

        foreach ($newChecksums as $relativePath => $newChecksum) {
            if (!isset($oldChecksums[$relativePath])) {
                continue;
            }

            $oldChecksum = $oldChecksums[$relativePath];

            if ($oldChecksum === $newChecksum) {
                continue;
            }

            if (isset($filesWithApiChanges[$relativePath])) {
                continue;
            }

            $fileChanges[] = new FileChange($relativePath, $oldChecksum, $newChecksum);
        }

        usort($fileChanges, fn(FileChange $a, FileChange $b) => strcmp($a->getRelativePath(), $b->getRelativePath()));

        return $fileChanges;
    }

    /**
     * Extract the set of files that have API changes.
     *
     * @param ApiChange[] $apiChanges
     * @return array<string, true>
     */
    private function getFilesWithApiChanges(array $apiChanges): array
    {
        $files = [];

        foreach ($apiChanges as $change) {
            $element = $change->getElement();
            $sourceFile = $element->getSourceFile();

            if ($sourceFile !== null) {
                $files[$sourceFile] = true;
            }

            $oldElement = $change->getOldElement();
            if ($oldElement !== null) {
                $oldSourceFile = $oldElement->getSourceFile();
                if ($oldSourceFile !== null) {
                    $files[$oldSourceFile] = true;
                }
            }
        }

        return $files;
    }
}
