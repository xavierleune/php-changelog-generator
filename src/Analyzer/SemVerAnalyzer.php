<?php

declare(strict_types=1);

namespace Leune\ChangelogGenerator\Analyzer;

use Leune\ChangelogGenerator\Model\ApiChange;

class SemVerAnalyzer
{
    public function analyzeSeverity(array $changes): string
    {
        $hasMajor = false;
        $hasMinor = false;

        foreach ($changes as $change) {
            if (!$change instanceof ApiChange) {
                continue;
            }

            switch ($change->getSeverity()) {
                case ApiChange::SEVERITY_MAJOR:
                    $hasMajor = true;
                    break;
                case ApiChange::SEVERITY_MINOR:
                    $hasMinor = true;
                    break;
            }
        }

        if ($hasMajor) {
            return ApiChange::SEVERITY_MAJOR;
        }

        if ($hasMinor) {
            return ApiChange::SEVERITY_MINOR;
        }

        return ApiChange::SEVERITY_PATCH;
    }

    public function shouldBumpMajor(array $changes): bool
    {
        return $this->analyzeSeverity($changes) === ApiChange::SEVERITY_MAJOR;
    }

    public function shouldBumpMinor(array $changes): bool
    {
        return $this->analyzeSeverity($changes) === ApiChange::SEVERITY_MINOR;
    }

    public function getRecommendedVersion(string $currentVersion, array $changes): string
    {
        $severity = $this->analyzeSeverity($changes);
        $versionParts = explode('.', $currentVersion);

        $major = (int) ($versionParts[0] ?? 0);
        $minor = (int) ($versionParts[1] ?? 0);
        $patch = (int) ($versionParts[2] ?? 0);

        switch ($severity) {
            case ApiChange::SEVERITY_MAJOR:
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case ApiChange::SEVERITY_MINOR:
                $minor++;
                $patch = 0;
                break;
            case ApiChange::SEVERITY_PATCH:
                $patch++;
                break;
        }

        return "{$major}.{$minor}.{$patch}";
    }
}
